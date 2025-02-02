<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Media;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MediaGenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_genres_to_media()
    {
        $user = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);
        $genres = Genre::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->postJson("/api/media/{$media->id}/genres", [
                'genre_ids' => $genres->pluck('id')->toArray()
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'genres');
    }
} 