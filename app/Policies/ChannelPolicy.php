<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function update(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id;
    }
} 