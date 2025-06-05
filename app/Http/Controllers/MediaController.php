<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Media; // Assuming you have a Media model
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\MediaService;
use App\Models\Channel;
use App\Services\ChannelMediaService;
use App\Services\GenreService;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    use AuthorizesRequests;

    protected $mediaService;
    protected $channelMediaService;
    protected $genreService;

    public function __construct(MediaService $mediaService, ChannelMediaService $channelMediaService, GenreService $genreService)
    {
        $this->mediaService = $mediaService;
        $this->channelMediaService = $channelMediaService;
        $this->genreService = $genreService;
    }

    public function upload(Request $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $request->validate([
            'media_file' => 'required|file|mimes:mp3,mpeg,mp4,mov,avi,flv,jpg,png|max:20480', // 20MB max
        ]);

        // Generate a unique hash for the file name
        $userId = auth()->id();
        $timestamp = time();
        $extension = $request->file('media_file')->getClientOriginalExtension();
        $hash = md5($userId . $timestamp);
        $fileName = "{$hash}.$extension"; // Only the file name, no path

        // Determine media type and store the file
        $mediaType = $this->determineMediaType($extension); // Keep media type in original case
        $store = strtoupper($mediaType); // Convert media type to uppercase for storage path
        $path = $request->file('media_file')->storeAs(env("MEDIA_{$store}_PATH"), $fileName);

        // Get the MIME type of the uploaded file
        $mimeType = $request->file('media_file')->getMimeType();

        // Initialize variables for metadata
        $mediaData = [
            'filename' => $fileName,
            'file_path' => $path,
            'media_type' => $mediaType, // Keep original case
            'user_id' => $userId,
            'cover_art' => env('MEDIA_COVERART_PATH') . '/default.jpg',
            'public' => 'public',
            'downloadable' => 'no',
            'type' => $mimeType,
        ];

        // Process based on media type
        if ($mediaType === 'audio') {
            $audioFileInfo = $this->mediaService->getAudioMetadata(storage_path('app/' . $path));

            // Check for errors in audio analysis
            if (isset($audioFileInfo['error'])) {
                return response()->json(['error' => $audioFileInfo['error']], 422);
            }

            // Extract audio metadata
            $mediaData['title'] = $audioFileInfo['tags']['id3v2']['title'][0] ?? null;
            $mediaData['track'] = $audioFileInfo['tags']['id3v2']['track_number'][0] ?? null;
            $mediaData['album'] = $audioFileInfo['tags']['id3v2']['album'][0] ?? null;
            $mediaData['year'] = $audioFileInfo['tags']['id3v2']['year'][0] ?? null;
            $mediaData['artist'] = $audioFileInfo['tags']['id3v2']['artist'][0] ?? null;
            $mediaData['size'] = (int) $audioFileInfo['filesize'] ?? 0;
            $mediaData['duration'] = $audioFileInfo['playtime_string'] ?? null;
            $mediaData['channelmode'] = $audioFileInfo['audio']['channels'] == 1 ? 'mono' : 'stereo';

        } elseif ($mediaType === 'video') {
            $videoFileInfo = $this->mediaService->getVideoMetadata(storage_path('app/' . $path));

            // Extract video metadata
            $mediaData['title'] = $videoFileInfo->get('tags')['title'] ?? null;
            $mediaData['size'] = (int) $videoFileInfo->get('size');
            $mediaData['duration'] = $videoFileInfo->get('duration');
            $mediaData['resolution'] = $videoFileInfo->get('width') . 'x' . $videoFileInfo->get('height');
            $mediaData['codec'] = $videoFileInfo->get('codec_name');
            $mediaData['frame_rate'] = $videoFileInfo->get('avg_frame_rate');
            $mediaData['aspect_ratio'] = $videoFileInfo->get('display_aspect_ratio');
            $mediaData['bitrate'] = $videoFileInfo->get('bit_rate');

        } elseif ($mediaType === 'image') {
            // Handle image files if necessary
            // You can add image-specific metadata extraction here if needed
        }

        // Create the media record
        $mediaRecord = Media::create($mediaData);

        return response()->json($mediaRecord, 201);
    }

    private function determineMediaType($extension)
    {
        $audioExtensions = ['mp3', 'wav', 'flac', 'aac'];
        $videoExtensions = ['mp4', 'mov', 'avi', 'flv'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($extension, $audioExtensions)) {
            return 'audio';
        } elseif (in_array($extension, $videoExtensions)) {
            return 'video';
        } elseif (in_array($extension, $imageExtensions)) {
            return 'image';
        }

        return 'unknown'; // Default case
    }

    public function update(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        try {
            // Validate the incoming request
            $request->validate([
                'title' => 'nullable|string|max:254',
                'album' => 'nullable|string|max:120',
                'year' => 'nullable|string|max:4',
                'artist' => 'nullable|string|max:120',
                'public' => 'nullable|in:public,private',
                'downloadable' => 'nullable|in:yes,no',
                'track' => 'nullable|integer',
            ]);

            // Update only the fields that are present in the request
            $media->update($request->only(['title', 'album', 'year', 'artist', 'public', 'downloadable', 'track']));

            // Get metadata fields to update in the file based on media type
            $metadata = [];
            if ($media->media_type === 'audio') {
                $metadata = $request->only([
                    'title',
                    'album',
                    'year',
                    'artist',
                    'track'
                ]);
            } elseif ($media->media_type === 'video') {
                $metadata = $request->only([
                    'title',
                    'artist',
                    'year'
                ]);
            }

            // Update the metadata after the database changes are successful
            $this->mediaService->updateMetadata($media, $metadata);

            return response()->json($media);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update media',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function show(Media $media)
    {
        $this->authorize('view', $media);
        return $media;
    }

    public function convertMedia(Request $request, Media $media)
    {
        // Update the input and output paths for video files
        $inputFile = storage_path("app/" . env('MEDIA_VIDEO_PATH') . "/{$media->filename}"); // Input path for video
        $outputFile = storage_path("app/" . env('MEDIA_AUDIO_PATH') . "{$media->filename}.mp4"); // Change to video directory

        // Use the MediaService to convert the video
        $this->mediaService->convertVideoMedia($inputFile, $outputFile, 'mp4'); // Ensure the format is correct

        return response()->json(['message' => 'Media converted successfully.']);
    }

    public function analyze(Request $request, Media $media)
    {
        // Validate the incoming request
        $request->validate([
            'file' => 'required|file', // Assuming you are analyzing a file
        ]);

        // Use the MediaService to analyze the media
        $analysisResult = $this->mediaService->analyzeMedia(storage_path("app/{$media->file_path}"));

        return response()->json($analysisResult);
    }

    public function updateCoverArt(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        $request->validate([
            'cover_art' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {
            $path = $this->mediaService
                         ->updateCoverArt($media, $request->file('cover_art'));

            return response()->json([
                'message'   => 'Cover art updated successfully.',
                'cover_art' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to update cover art',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function getStoragePath($mediaType, $fileName)
    {
        return storage_path("app/" . env("MEDIA_{$mediaType}_PATH") . "/{$fileName}");
    }

    public function attachGenres(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        try {
            $request->validate([
                'genre_ids' => 'required|array',
                'genre_ids.*' => 'exists:genres,id'
            ]);

            $this->genreService->attachToMedia($media, $request->genre_ids);

            return response()->json([
                'message' => 'Genres attached successfully',
                'media' => $media->load('genres')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to attach genres to media',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function detachGenres(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        try {
            $request->validate([
                'genre_ids' => 'required|array',
                'genre_ids.*' => 'exists:genres,id'
            ]);

            $this->genreService->detachFromMedia($media, $request->genre_ids);

            return response()->json([
                'message' => 'Genres detached successfully',
                'media' => $media->load('genres')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to detach genres from media',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Media $media)
    {
        $this->authorize('delete', $media);
        
        // Soft delete the media
        $media->delete();
        
        return response()->json(null, 204);
    }

    public function trashed(Request $request)
    {
        $media = Media::onlyTrashed()
            ->where('user_id', auth()->id())
            ->paginate(15);

        return response()->json($media);
    }

    public function viewTrashed(Media $media)
    {
        $this->authorize('viewTrashed', $media);

        // Check if the media is actually trashed
        if (!$media->trashed()) {
            return response()->json([
                'error' => 'Media not found in trash',
                'message' => 'The specified media is not in the trash'
            ], 404);
        }

        // Load relationships
        $media->load(['channels', 'genres']);

        return response()->json([
            'data' => $media
        ]);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:media,id'
        ]);

        $restored = [];
        $failed = [];

        foreach ($request->ids as $id) {
            try {
                $media = Media::onlyTrashed()->find($id);
                
                if (!$media) {
                    $failed[] = $id;
                    continue;
                }

                // Check if user is authorized to restore this media
                if (!$this->authorize('restore', $media)) {
                    $failed[] = $id;
                    continue;
                }

                $media->restore();
                $restored[] = $id;
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }

        return response()->json([
            'message' => 'Media restoration completed',
            'restored' => $restored,
            'failed' => $failed
        ]);
    }
}
