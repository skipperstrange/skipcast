<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Media; // Assuming you have a Media model
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\MediaService;
use App\Models\Channel;

class MediaController extends Controller
{
    use AuthorizesRequests;

    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function upload(Request $request)
    {
        // Validate the incoming request
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
        // Authorize the user to update the media
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

    public function attachChannels(Request $request, Media $media)
    {
        // Authorize the user to attach channels to this media
        $this->authorize('update', $media);

        try {
            $request->validate([
                'channel_ids' => 'required|array',
                'channel_ids.*' => 'exists:channels,id'
            ]);

            // Attach channels to media with active status
            foreach ($request->channel_ids as $channelId) {
                $media->channels()->attach($channelId, [
                    'active' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'message' => 'Channels attached successfully',
                'media' => $media->load('channels')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to attach channels to media',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Media $media)
    {
        // Check if media is private and user is not authorized
        if ($media->public === 'private' && !auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

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
        // Validate the incoming request
        $request->validate([
            'cover_art' => 'required|image|mimes:jpg,jpeg,png|max:2048', // Validate image file
        ]);

        // Generate a unique filename for the cover art
        $extension = $request->file('cover_art')->getClientOriginalExtension();
        $coverArtFileName = "{$media->filename}.{$extension}"; // Use media filename
        $coverArtPath = storage_path("app/" . env('MEDIA_COVERART_PATH') . "/{$coverArtFileName}");

        // Store the uploaded cover art image
        $request->file('cover_art')->move(storage_path('app/' . env('MEDIA_COVERART_PATH')), $coverArtFileName);

        // Update the media record with the new cover art path
        $media->cover_art = env('MEDIA_COVERART_PATH') . "/{$coverArtFileName}";
        $media->save();

        return response()->json(['message' => 'Cover art updated successfully.', 'cover_art' => $media->cover_art]);
    }

    private function getStoragePath($mediaType, $fileName)
    {
        return storage_path("app/" . env("MEDIA_{$mediaType}_PATH") . "/{$fileName}");
    }

    /**
     * Detach channels from the media.
     *
     * @param Request $request
     * @param Media $media
     * @return JsonResponse
     */
    public function detachChannels(Request $request, Media $media)
    {
        $this->authorize('update', $media);

        try {
            $request->validate([
                'channel_ids' => 'required|array',
                'channel_ids.*' => 'exists:channels,id'
            ]);

            $media->channels()->detach($request->channel_ids);

            return response()->json([
                'message' => 'Channels detached successfully',
                'media' => $media->load('channels')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to detach channels from media',
                'message' => $e->getMessage()
            ], 500);
        }
    }
} 