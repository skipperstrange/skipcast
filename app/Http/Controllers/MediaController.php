<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Media; // Assuming you have a Media model
use Plutuss\Facades\MediaAnalyzer; // Import the MediaAnalyzer facade
use Illuminate\Support\Facades\Log;
use getID3; // Import the getID3 library
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'media_file' => 'required|file|mimes:mp3,mpeg,mp4,mov,avi,flv|max:20480', // 20MB max
            ]);
        } catch (\Exception $e) {
            Log::error('File upload error: ' . $e->getMessage());
            return response()->json(['message' => 'The media file failed to upload.'], 422);
        }

        // Generate a unique hash for the file name
        $userId = auth()->id();
        $timestamp = time();
        $extension = $request->file('media_file')->getClientOriginalExtension();
        $hash = md5($userId . $timestamp);
        $fileName = "{$hash}.$extension"; // Only the file name, no path

        // Store the uploaded file
        $path = $request->file('media_file')->storeAs('media/audio', $fileName);

        // Log the path for debugging
        Log::info('File stored at: ' . storage_path('app/' . $path));

        // Initialize getID3
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze(storage_path('app/' . $path));

        // Log file info for debugging
        Log::info('File info: ' . json_encode($fileInfo));

        // Check for errors
        if (isset($fileInfo['error'])) {
            return response()->json(['error' => $fileInfo['error']], 422);
        }

        // Extract cover art if available
        $coverArtPath = 'media/coverart/default.jpg'; // Default cover art path
        if (isset($fileInfo['comments']['picture'][0])) {
            $coverArt = $fileInfo['comments']['picture'][0];
            
            // Check if 'data' key exists
            if (isset($coverArt['data'])) {
                $imageData = $coverArt['data']; // Get the image data
                
                // Save the cover art to the public directory with the same name as the media file
                $coverArtFileName = storage_path("app/media/coverart/{$hash}.jpg"); // Define the path for the cover art
                file_put_contents($coverArtFileName, $imageData); // Save to public path
                $coverArtPath = "media/coverart/{$hash}.jpg"; // Update the cover art path
            } else {
                Log::warning('Cover art data not found in the extracted data.');
            }
        }

        // Create the media record
        $mediaRecord = Media::create([
            'title' => $fileInfo['tags']['id3v2']['title'][0] ?? null,
            'track' => $fileInfo['tags']['id3v2']['track_number'][0] ?? null,
            'album' => $fileInfo['tags']['id3v2']['album'][0] ?? null,
            'year' => $fileInfo['tags']['id3v2']['year'][0] ?? null,
            'artist' => $fileInfo['tags']['id3v2']['artist'][0] ?? null,
            'type' => $fileInfo['mime_type'] ?? null,
            'channelmode' => $fileInfo['audio']['channels'] == 1 ? 'mono' : 'stereo',
            'public' => 'public', // Default value
            'downloadable' => 'no', // Default value
            'size' => (int) $fileInfo['filesize'] ?? 0,
            'duration' => $fileInfo['playtime_string'] ?? null, // Extract duration
            'user_id' => $userId, // Assuming the user is authenticated
            'cover_art' => $coverArtPath, // Store cover art path if available
            'filename' => $fileName, // Store the filename
            'file_path' => $path, // Store the file path
        ]);

        return response()->json($mediaRecord, 201);
    }

    public function update(Request $request, Media $media)
    {
        // Authorize the user to update the media
        $this->authorize('update', $media);

        $request->validate([
            'title' => 'nullable|string|max:254',
            'album' => 'nullable|string|max:120',
            'year' => 'nullable|string|max:4',
            'artist' => 'nullable|string|max:120',
            'public' => 'nullable|in:public,private',
            'downloadable' => 'nullable|in:yes,no',
        ]);

        $media->update($request->only(['title', 'album', 'year', 'artist', 'public', 'downloadable']));

        return response()->json($media);
    }

    public function attachChannels(Request $request, Media $media)
    {
        try {
            $request->validate([
                'channel_ids' => 'required|array', // Validate channel_ids as an array
                'channel_ids.*' => 'exists:channels,id', // Each channel_id must exist in the channels table
            ]);
        } catch (\Exception $e) {
            Log::error('Attach channels error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to attach channels.'], 422);
        }

        // Associate the media with the channels
        $channelIds = $request->input('channel_ids');
        foreach ($channelIds as $channelId) {
            DB::table('channel_media')->insert([
                'channel_id' => $channelId,
                'media_id' => $media->id,
                'active' => 'active', // Default value
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Channels attached successfully.'], 200);
    }

    public function show(Media $media)
    {
        // Check if media is private and user is not authorized
        if ($media->public === 'private' && !auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $media;
    }
} 