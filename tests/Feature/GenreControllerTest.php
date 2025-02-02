<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_genres()
    {
        Genre::factory()->count(3)->create();

        $response = $this->getJson('/api/genres');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_create_genre()
    {
        $user = User::factory()->create(['role' => 'dj']);

        $response = $this->actingAs($user)
            ->postJson('/api/genres', ['genre' => 'Rock']);

        $response->assertStatus(201)
            ->assertJson(['genre' => 'Rock']);
    }
}

class ChannelGenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_genres_to_channel()
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['user_id' => $user->id]);
        $genres = Genre::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/genres", [
                'genre_ids' => $genres->pluck('id')->toArray()
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'genres');
    }
} 