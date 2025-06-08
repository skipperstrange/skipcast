<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use App\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use App\Traits\HandlesApiResponses;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\ChannelMediaService;
use App\Services\GenreService;
use App\Services\StreamService;

class ChannelController extends Controller
{
    use AuthorizesRequests, HandlesApiResponses;

    protected $channelMediaService;
    protected $genreService;
    protected $streamService;

    public function __construct(
        ChannelMediaService $channelMediaService, 
        GenreService $genreService,
        StreamService $streamService
    ) {
        $this->channelMediaService = $channelMediaService;
        $this->genreService = $genreService;
        $this->streamService = $streamService;
    }

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
            'privacy' => $request->privacy ?? 'public',
            'state' => 'off',
            'active' => true
        ]);
        // Attach genres if provided
        if ($request->has('genre_ids')) {
            try {
                $this->genreService->attachToChannel($channel, $request->genre_ids);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to attach genres to channel',
                    'message' => $e->getMessage()
                ], 400); // Return a 400 Bad Request status
            }
        }

        return response()->json([
            'message' => 'Channel created successfully',
            'channel' => $channel->load('genres')
        ]);
    }

    public function show(Channel $channel): JsonResponse
    {
        // If channel is not active, treat it as not found
        if (!$channel->active) {
            throw new ModelNotFoundException("Channel not found or inactive", 404);
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

        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update channel state',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start streaming a channel
     */
    public function startStream(Channel $channel)
    {
        try {
            // Generate Liquidsoap config and playlist
            $this->channelMediaService->saveLiquidsoapConfig($channel);
            $this->channelMediaService->generatePlaylistFile($channel);

            // Start the stream via Kafka
            $this->streamService->startStream($channel);

            return response()->json([
                'message' => 'Stream start command sent successfully',
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start stream', [
                'error' => $e->getMessage(),
                'channel' => $channel->slug
            ]);
            
            return response()->json([
                'error' => 'Failed to start stream',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop streaming a channel
     */
    public function stopStream(Channel $channel)
    {
        try {
            // Stop the stream via Kafka
            $this->streamService->stopStream($channel);

            return response()->json([
                'message' => 'Stream stop command sent successfully',
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to stop stream', [
                'error' => $e->getMessage(),
                'channel' => $channel->slug
            ]);
            
            return response()->json([
                'error' => 'Failed to stop stream',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current status of a channel's stream
     */
    public function getStreamStatus(Channel $channel)
    {
        try {
            $this->streamService->checkStreamStatus($channel);

            return response()->json([
                'message' => 'Stream status check initiated',
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check stream status', [
                'error' => $e->getMessage(),
                'channel' => $channel->slug
            ]);
            
            return response()->json([
                'error' => 'Failed to check stream status',
                'message' => $e->getMessage()
            ], 500);
        }
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

   

    public function attachGenres(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        try {
            $request->validate([
                'genre_ids' => 'required|array',
                'genre_ids.*' => 'exists:genres,id'
            ]);

            $this->genreService->attachToChannel($channel, $request->genre_ids);

            return response()->json([
                'message' => 'Genres attached successfully',
                'channel' => $channel->load('genres')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to attach genres to channel',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function detachGenres(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        try {
            $request->validate([
                'genre_ids' => 'required|array',
                'genre_ids.*' => 'exists:genres,id'
            ]);

            $this->genreService->detachFromChannel($channel, $request->genre_ids);

            return response()->json([
                'message' => 'Genres detached successfully',
                'channel' => $channel->load('genres')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to detach genres from channel',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function attachMedia(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        try {
            $request->validate([
                'media_ids' => 'required|array',
                'media_ids.*' => 'exists:media,id'
            ]);

            $this->channelMediaService->attachMedia($channel, $request->media_ids);
   
            // Regenerate Liquidsoap config and playlist
            $this->channelMediaService->saveLiquidsoapConfig($channel);

            // Generate playlist file
            $this->channelMediaService->generatePlaylistFile($channel);


            return response()->json([
                'message' => 'Media attached to channel successfully',
                'channel' => $channel->load('media')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to attach media to channel',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function detachMedia(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        try {
            $request->validate([
                'media_ids' => 'required|array',
                'media_ids.*' => 'exists:media,id'
            ]);

            $this->channelMediaService->detachMedia($channel, $request->media_ids);
            
            // Regenerate Liquidsoap config and playlist
            $this->channelMediaService->saveLiquidsoapConfig($channel);

            return response()->json([
                'message' => 'Media detached from channel successfully',
                'channel' => $channel->load('media')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to detach media from channel',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return the authenticated user's soft-deleted channels.
     */
    public function trashed(Request $request)
    {
        $channels = Channel::onlyTrashed()
            ->where('user_id', auth()->id())
            ->paginate(15);

        return response()->json($channels);
    }

    public function viewTrashed(Channel $channel)
    {
        // Check if the channel is actually trashed
        if (!$channel->trashed()) {
            return response()->json([
                'error' => 'Channel not found in trash',
                'message' => 'The specified channel is not in the trash'
            ], 404);
        }

        // Check if the user owns the channel
        if ($channel->user_id !== auth()->id()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to view this channel'
            ], 403);
        }

        // Load the media relationship
        $channel->load('media');

        return response()->json([
            'data' => $channel
        ]);
    }

    /**
     * Restore soft-deleted channels by single ID or array of IDs.
     */
    public function restore(Request $request, $id = null)
    {
        try {
            // If ID is in the URL, restore single channel
            if ($id !== null) {
                $channel = Channel::withTrashed()->findOrFail($id);
                $this->authorize('restore', $channel);
                $channel->restore();

                return response()->json([
                    'message' => 'Channel restored successfully',
                    'channel' => $channel
                ]);
            }
            
            // Otherwise, look for IDs array in request body for bulk restore
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:channels,id'
            ]);
            
            $channels = [];
            foreach ($request->ids as $channelId) {
                $channel = Channel::withTrashed()->findOrFail($channelId);
                $this->authorize('restore', $channel);
                $channel->restore();
                $channels[] = $channel;
            }
            
            return response()->json([
                'message' => count($channels) > 1 ? 
                    'Channels restored successfully' : 
                    'Channel restored successfully',
                'channels' => $channels
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Channel not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to restore channel(s)',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
