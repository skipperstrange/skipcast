<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Media; // Assuming you have a Media model
use getID3;

class MediaController extends Controller
{
    // Ensure the user is authenticated for all methods in this controller
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['upload']);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'media_file' => 'required|file|mimes:mp3,mpeg,mp4,mov,avi,flv|max:20480', // 20MB max
        ]);

        // Store the uploaded file
        $path = $request->file('media_file')->store('media');

        // Initialize getID3
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze(storage_path('app/' . $path));

        // Check for errors
        if (isset($fileInfo['error'])) {
            return response()->json(['error' => $fileInfo['error']], 422);
        }

        // Extract metadata
        $media = Media::create([
            'title' => $fileInfo['tags']['id3v2']['title'][0] ?? null,
            'track' => $fileInfo['tags']['id3v2']['track_number'][0] ?? null,
            'album' => $fileInfo['tags']['id3v2']['album'][0] ?? null,
            'year' => $fileInfo['tags']['id3v2']['year'][0] ?? null,
            'artist' => $fileInfo['tags']['id3v2']['artist'][0] ?? null,
            'type' => $fileInfo['mime_type'] ?? null,
            'channelmode' => $fileInfo['audio']['channels'] == 1 ? 'mono' : 'stereo',
            'public' => 'public', // Default value
            'downloadable' => 'no', // Default value
            'size' => $fileInfo['filesize'] ?? 0,
            'duration' => isset($fileInfo['playtime_string']) ? $fileInfo['playtime_string'] : null, // Extract duration
            'user_id' => auth()->id(), // Assuming the user is authenticated
        ]);

        return response()->json($media, 201);
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
} 