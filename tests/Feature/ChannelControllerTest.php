<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Channel;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChannelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_media_for_public_channel()
    {
        $channel = Channel::factory()->create(['privacy' => 'public']);
        $publicMedia = Media::factory()->create(['public' => 'public']);
        $privateMedia = Media::factory()->create(['public' => 'private']);
        $channel->media()->attach([$publicMedia->id, $privateMedia->id]);

        $response = $this->getJson("/api/channels/{$channel->id}/media");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data') // Only public media should be returned
            ->assertJsonFragment(['id' => $publicMedia->id])
            ->assertJsonMissing(['id' => $privateMedia->id]);
    }

    public function test_list_media_for_private_channel_unauthorized()
    {
        $channel = Channel::factory()->create(['privacy' => 'private']);
        $response = $this->getJson("/api/channels/{$channel->id}/media");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_list_media_for_private_channel_authorized()
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['privacy' => 'private', 'user_id' => $user->id]);
        $publicMedia = Media::factory()->create(['public' => 'public']);
        $privateMedia = Media::factory()->create(['public' => 'private']);
        $channel->media()->attach([$publicMedia->id, $privateMedia->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/media");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data') // Both public and private media should be returned
            ->assertJsonFragment(['id' => $publicMedia->id])
            ->assertJsonFragment(['id' => $privateMedia->id]);
    }
} 