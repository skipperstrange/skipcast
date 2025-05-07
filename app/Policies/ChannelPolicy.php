<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    /**
     * Determine if the user can update the channel
     */
    public function update(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id || $user->role === 'admin';
    }

    /**
     * Determine if the user can delete the channel
     */
    public function delete(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id || $user->role === 'admin';
    }

    /**
     * Determine if the user can manage channel state
     */
    public function manageState(User $user, Channel $channel): bool
    {
        return ($user->id === $channel->user_id && $user->role === 'dj') || $user->role === 'admin';
    }

    /**
     * Determine if the user can restore a soft-deleted channel
     */
    public function restore(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id || $user->role === 'admin';
    }
} 