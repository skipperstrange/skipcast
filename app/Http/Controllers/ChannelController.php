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

    public function updateState(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('manageState', $channel);

        $request->validate([
            'state' => ['required', 'in:on,off']
        ]);

        $channel->update(['state' => $request->state]);

        return response()->json($channel);
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
} 