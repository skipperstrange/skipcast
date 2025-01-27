<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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
} 