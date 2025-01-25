<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChannelLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_exceed_free_channel_limit(): void
    {
        $user = User::factory()->create(['role' => 'dj']);
        Sanctum::actingAs($user);

        // Create 5 channels (free limit)
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/channels', [
                'name' => "Channel {$i}",
                'category' => 'music'
            ]);
            $response->assertCreated();
        }

        // Try to create 6th channel
        $response = $this->postJson('/api/channels', [
            'name' => 'Channel 6',
            'category' => 'music'
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'You have reached the maximum number of free channels'
            ]);
    }
} 