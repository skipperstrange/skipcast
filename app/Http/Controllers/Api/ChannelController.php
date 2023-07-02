<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Channel;


class ChannelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $channels = Channel::all();
        return response()->json($channels);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validatedData = $request->validate([
            'name' => 'required|string|max:60',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
        ]);

        $channel = new Channel();
        $channel->fill($validatedData);
        $channel->save();

        return response()->json($channel);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $channel = Channel::findOrFail($id);
        return response()->json($channel);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $channel = Channel::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'required|string|max:60',
            'description' => 'nullable|string',
            //'user_id' => 'required|exists:users,id',
        ]);

        $channel->fill($validatedData);
        $channel->save();

        return response()->json($channel);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $channel = Channel::findOrFail($id);
        $channel->delete();

        return response()->json(['message' => 'Channel deleted successfully']);
    }
}
