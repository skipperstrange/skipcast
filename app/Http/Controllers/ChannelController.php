<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $channels = Channel::query()
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->privacy, fn($q) => $q->where('privacy', $request->privacy))
            ->get();

        return response()->json($channels);
    }

    public function store(Request $request): JsonResponse
    {
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
            'active' => 'active'
        ]);

        return response()->json($channel, 201);
    }

    public function show(Channel $channel): JsonResponse
    {
        return response()->json($channel);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        $channel->update($request->only([
            'name',
            'description',
            'category',
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
        $this->authorize('update', $channel);

        $request->validate([
            'state' => ['required', 'in:on,off']
        ]);

        $channel->update(['state' => $request->state]);

        return response()->json($channel);
    }
} 