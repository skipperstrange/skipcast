<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class ChannelController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $channels = Channel::query()
            ->where('active', true)
            ->when($request->genre, fn($q) => $q->where('genre', $request->genre))
            ->when($request->privacy, fn($q) => $q->where('privacy', $request->privacy))
            ->when($request->search, function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            })
            ->when($request->sort, function($q) use ($request) {
                $direction = $request->order ?? 'asc';
                $q->orderBy($request->sort, $direction);
            })
            ->paginate($request->per_page ?? 15);

        return response()->json($channels);
    }

    public function store(Request $request): JsonResponse
    {
        // Log the incoming request data
       // \Log::info('Request data:', $request->all());

        if (auth()->user()->role !== 'dj' && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Only DJs can create channels'], 403);
        }

        // Check channel limit for non-premium users
        if (auth()->user()->hasReachedChannelLimit()) {
            return response()->json([
                'message' => 'You have reached the maximum number of free channels'
            ], 403);
        }

        $channel = Channel::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'genre' => $request->genre,
            'privacy' => $request->privacy ?? 'public',
            'state' => 'off',
            'active' => true
        ]);

        // Attach genres if provided
        if ($request->has('genre_ids')) {
            $channel->genres()->sync($request->genre_ids);
        }

        return response()->json($channel, 201);
    }

    public function show(Channel $channel): JsonResponse
    {
        if (!$channel->active) {
            return response()->json(['message' => 'Channel not found'], 404);
        }

        // Load relationships if requested
        if (request()->has('with')) {
            $relations = explode(',', request('with'));
            $allowedRelations = ['user']; // Add more as needed
            $validRelations = array_intersect($relations, $allowedRelations);
            $channel->load($validRelations);
        }

        return response()->json($channel);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        $channel->update($request->only([
            'name',
            'description',
            'genre',
            'privacy'
        ]));

        return response()->json($channel);
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $this->authorize('delete', $channel);

        $channel->delete();

        return response()->json(null, 204);
    }

    public function updateState(Request $request, Channel $channel)
    {
        $request->validate([
            'state' => 'required|string|in:on,off',
        ]);

        // Update the channel state
        $channel->update(['state' => $request->state]);

        // Start the stream if the state is set to 'on'
        if ($request->state === 'on') {
            return $this->startStream($channel);
        }

        // If the state is set to 'off', stop the stream
        if ($request->state === 'off') {
            return $this->stopStream($channel);
        }

        return response()->json(['message' => 'Channel state updated successfully']);
    }

    public function showWithMediaAndUser($slug, Request $request)
    {
        $query = Channel::where('slug', $slug);

        // Check if the 'with' query parameter is present
        if ($request->has('with') && $request->input('with') === 'user') {
            $query->with('user'); // Eager load the user relationship
        }

        $channel = $query->with('media')->firstOrFail(); // Eager load media

        return response()->json($channel);
    }

    public function updateMediaOrder(Request $request, Channel $channel)
    {
        $request->validate([
            'media' => 'required|array',
            'media.*.id' => 'required|exists:media,id',
            'media.*.order' => 'required|integer',
        ]);

        // Get all media items for this channel
        $existingMedia = DB::table('channel_media')
            ->where('channel_id', $channel->id)
            ->orderBy('list_order')
            ->get();

        foreach ($request->media as $item) {
            if ($item['order'] > count($existingMedia)) {
                return response()->json(['error' => 'Invalid order position'], 400);
            }

            // Get the current order of the media being moved
            $currentOrder = DB::table('channel_media')
                ->where('channel_id', $channel->id)
                ->where('media_id', $item['id'])
                ->value('list_order');

            // If moving to a higher position
            if ($item['order'] > $currentOrder) {
                // Decrement orders between current and new position
                DB::table('channel_media')
                    ->where('channel_id', $channel->id)
                    ->whereBetween('list_order', [$currentOrder + 1, $item['order']])
                    ->decrement('list_order');
            }
            // If moving to a lower position
            elseif ($item['order'] < $currentOrder) {
                // Increment orders between new and current position
                DB::table('channel_media')
                    ->where('channel_id', $channel->id)
                    ->whereBetween('list_order', [$item['order'], $currentOrder - 1])
                    ->increment('list_order');
            }

            // Update the moved media's order
            DB::table('channel_media')
                ->where('channel_id', $channel->id)
                ->where('media_id', $item['id'])
                ->update(['list_order' => $item['order']]);
        }

        // Update the stream configuration after changing the order
        $this->updateStreamConfiguration($channel);

        return response()->json(['message' => 'Media order updated successfully.']);
    }

    public function listMedia(Request $request, Channel $channel)
    {
        // Check if channel is private and user is not authorized
        if ($channel->privacy === 'private' && !$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get media based on privacy
        $media = $channel->media()
            ->where(function ($query) use ($request, $channel) {
                // Include public media for all users
                $query->where('public', 'public');

                // Include private media for the channel owner
                if ($request->user() && $request->user()->id === $channel->user_id) {
                    $query->orWhere('public', 'private');
                }
            })
            ->paginate(15);

        return response()->json([
            "data" => $media->items(),
            "meta" => [
                "current_page" => $media->currentPage(),
                "per_page" => $media->perPage(),
                "total" => $media->total(),
            ]
        ]);
    }

    public function getStreamConfiguration(Channel $channel)
    {
        // Check if the channel is public
        if ($channel->privacy === 'private' && !auth()->check()) {
            return response()->json(['error' => 'Unauthorized to access this channel'], 403);
        }

        // Retrieve media associated with the channel
        $mediaItems = $channel->media()->orderBy('list_order')->get();

        // Filter media based on privacy
        $filteredMediaItems = $mediaItems->filter(function ($media) {
            // Allow public media for all users
            if ($media->public === 'public') {
                return true;
            }

            // Allow private media only for the owner
            return auth()->check() && auth()->id() === $media->user_id;
        });

        // Generate Liquidsoap configuration based on privacy
        $liquidsoapConfig = $this->generateLiquidsoapConfig($filteredMediaItems, $channel->privacy);

        return response()->json(['config' => $liquidsoapConfig]);
    }

    private function generateLiquidsoapConfig($mediaItems, $privacy)
    {
        $config = "## Liquidsoap Stream Configuration\n\n";
        $config .= "## Define the audio sources\n";

        foreach ($mediaItems as $media) {
            $filePath = storage_path("app/media/audio/{$media->filename}"); // Adjust the path as necessary
            $config .= "source_{$media->id} = single(\"{$filePath}\")\n";
        }

        $config .= "\n## Define the playlist\n";
        $config .= "playlist = [";

        foreach ($mediaItems as $media) {
            $config .= "source_{$media->id}, ";
        }

        $config = rtrim($config, ', ') . "]\n\n"; // Remove trailing comma

        // Output configuration based on privacy
        if ($privacy === 'public') {
            $config .= "## Public Output configuration\n";
            $config .= "output.icecast(%mp3, host = \"" . env('LIQUIDSOAP_HOST') . "\", port = " . env('LIQUIDSOAP_PORT') . ", password = \"" . env('LIQUIDSOAP_PASSWORD') . "\", mount = \"" . env('LIQUIDSOAP_PUBLIC_MOUNT') . "\", playlist)\n";
        } else {
            $config .= "## Private Output configuration\n";
            $config .= "output.icecast(%mp3, host = \"" . env('LIQUIDSOAP_HOST') . "\", port = " . env('LIQUIDSOAP_PORT') . ", password = \"" . env('LIQUIDSOAP_PASSWORD') . "\", mount = \"" . env('LIQUIDSOAP_PRIVATE_MOUNT') . "\", playlist)\n";
        }

        return $config;
    }

    private function isStreamRunning(Channel $channel): bool
    {
        // Check if the Liquidsoap process is running for this channel
        $output = [];
        $returnVar = 0;
        exec("pgrep -f 'liquidsoap.*channel_{$channel->id}'", $output, $returnVar);
        
        return $returnVar === 0; // If the command returns 0, the process is running
    }

    public function startStream(Channel $channel)
    {
        // Check if the stream is already running
        if ($this->isStreamRunning($channel)) {
            return response()->json(['message' => 'Stream is already running'], 200);
        }

        // Save the Liquidsoap configuration with the appropriate folder
        $configPath = $this->saveLiquidsoapConfig($this->generateLiquidsoapConfig($channel->media, $channel->privacy), $channel);

        // Execute Liquidsoap on the remote server
        $remoteHost = env('LIQUIDSOAP_HOST'); // The IP or domain of the remote server
        $remoteUser = env('LIQUIDSOAP_USER'); // The SSH username
        $command = "ssh {$remoteUser}@{$remoteHost} 'liquidsoap {$configPath}' > /dev/null 2>&1 &"; // Run in the background
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            return response()->json(['error' => 'Failed to start stream'], 500);
        }

        // Update channel status to 'on air'
        $channel->update(['state' => 'on']);

        return response()->json(['message' => 'Stream started successfully']);
    }

    public function stopStream(Channel $channel)
    {
        // Check if the stream is running
        if (!$this->isStreamRunning($channel)) {
            return response()->json(['message' => 'Stream is not running'], 200);
        }

        // Stop the Liquidsoap process using the channel slug
        exec("pkill -f 'liquidsoap.*{$channel->slug}'");

        // Update channel status to 'off air'
        $channel->update(['state' => 'off']);

        return response()->json(['message' => 'Stream stopped successfully']);
    }

    private function saveLiquidsoapConfig($config, $channel)
    {
        // Determine the storage path based on privacy
        $folder = $channel->privacy === 'public' ? 'public' : 'private';
        $filePath = storage_path("app/liquidsoap/{$folder}/{$channel->slug}.liq");

        // Ensure the directory exists
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        // Save the configuration to the file
        file_put_contents($filePath, $config);
        return $filePath;
    }

    public function getStreamUrls(Channel $channel)
    {
        // Get the host and port from the environment variables
        $host = env('LIQUIDSOAP_HOST');
        $port = env('LIQUIDSOAP_PORT');

        // Construct the stream URLs based on the channel's privacy
        $publicStreamUrl = "http://{$host}:{$port}/" . env('LIQUIDSOAP_PUBLIC_MOUNT');
        $privateStreamUrl = "http://{$host}:{$port}/" . env('LIQUIDSOAP_PRIVATE_MOUNT');

        // Return the appropriate URL based on the channel's privacy
        if ($channel->privacy === 'public') {
            return response()->json(['stream_url' => $publicStreamUrl]);
        } elseif ($channel->privacy === 'private' && auth()->check() && auth()->id() === $channel->user_id) {
            return response()->json(['stream_url' => $privateStreamUrl]);
        } else {
            return response()->json(['error' => 'Unauthorized to access this stream'], 403);
        }
    }

    public function addMedia(Request $request, Channel $channel)
    {
        // Logic to add media...
        
        // Update the stream configuration after adding media
        $this->updateStreamConfiguration($channel);
    }

    public function deleteMedia(Channel $channel, $mediaId)
    {
        // Logic to delete media...
        
        // Update the stream configuration after deleting media
        $this->updateStreamConfiguration($channel);
    }

    private function updateStreamConfiguration(Channel $channel)
    {
        // Regenerate the Liquidsoap configuration
        $configPath = $this->saveLiquidsoapConfig($this->generateLiquidsoapConfig($channel->media, $channel->privacy), $channel);

        // Optionally restart the stream if it's running
        if ($this->isStreamRunning($channel)) {
            exec("pkill -f 'liquidsoap.*{$channel->slug}'"); // Stop the current stream
            $this->startStream($channel); // Restart with the updated configuration
        }
    }

    public function attachGenres(Request $request, Channel $channel)
    {
        \Log::info('Attaching genres to channel:', [
            'channel_id' => $channel->id,
            'genre_ids' => $request->genre_ids,
        ]);

        $request->validate([
            'genre_ids' => 'required|array',
            'genre_ids.*' => 'exists:genres,id', // Ensure each genre ID exists in the genres table
        ]);

        // Attach genres to the channel
        $channel->genres()->sync($request->genre_ids);

        return response()->json(['message' => 'Genres attached successfully.'], 200);
    }
} 