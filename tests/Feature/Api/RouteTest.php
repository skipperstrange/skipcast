<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Media;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_test_route_returns_success(): void
    {
        $response = $this->getJson('/api/test');

        $response->assertOk()
            ->assertJson(['message' => 'API is working']);
    }

    public function test_ping_route_returns_pong(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertOk()
            ->assertSee('pong');
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/channels');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/channels');

        $response->assertOk();
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'username', 'email', 'password', 'role']);
    }

    public function test_successful_registration(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'dj'
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'role'
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'email' => 'test@example.com'
        ]);
    }

    public function test_login_requires_credentials(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_social_auth_routes_are_accessible(): void
    {
        $providers = ['google', 'facebook'];

        foreach ($providers as $provider) {
            $response = $this->get("/api/auth/{$provider}");
            $response->assertRedirect();
        }
    }

    public function test_channel_crud_operations_require_authentication(): void
    {
        // List channels
        $this->getJson('/api/channels')->assertUnauthorized();

        // Create channel
        $this->postJson('/api/channels', [
            'name' => 'Test Channel',
            'category' => 'music'
        ])->assertUnauthorized();

        // Update channel
        $this->putJson('/api/channels/1', [
            'name' => 'Updated Channel'
        ])->assertUnauthorized();

        // Delete channel
        $this->deleteJson('/api/channels/1')->assertUnauthorized();

        // Update channel state
        $this->putJson('/api/channels/1/state', [
            'state' => 'on'
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_manage_channels(): void
    {
        $user = User::factory()->create(['role' => 'dj']);
        Sanctum::actingAs($user);

        // Create channel
        $response = $this->postJson('/api/channels', [
            'name' => 'Test Channel',
            'category' => 'music'
        ]);
        $response->assertCreated();
        $channelId = $response->json('id');

        // Get channel
        $this->getJson("/api/channels/{$channelId}")
            ->assertOk()
            ->assertJson(['name' => 'Test Channel']);

        // Update channel
        $this->putJson("/api/channels/{$channelId}", [
            'name' => 'Updated Channel'
        ])
        ->assertOk()
        ->assertJson(['name' => 'Updated Channel']);

        // Update channel state
        $this->putJson("/api/channels/{$channelId}/state", [
            'state' => 'on'
        ])
        ->assertOk()
        ->assertJson(['state' => 'on']);

        // Delete channel
        $this->deleteJson("/api/channels/{$channelId}")
            ->assertNoContent();
    }

    public function test_authenticated_user_can_upload_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/media/upload', [
            'media_file' => UploadedFile::fake()->create('audio.mp3', 5000, 'audio/mpeg'),
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'title', 'artist', 'duration', 'user_id']);
    }

    public function test_authenticated_user_can_update_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/media/{$media->id}", [
            'title' => 'Updated Title',
            'album' => 'Updated Album',
            'year' => '2023',
            'artist' => 'Updated Artist',
            'public' => 'public',
            'downloadable' => 'yes',
        ]);

        $response->assertOk()
            ->assertJson(['title' => 'Updated Title']);
    }

    public function test_user_can_soft_delete_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $media = Media::factory()->create(['user_id' => $user->id]);

        // Soft delete the media
        $response = $this->deleteJson("/api/media/{$media->id}");
        $response->assertNoContent();

        // Assert the media is soft deleted
        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_user_can_restore_soft_deleted_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $media = Media::factory()->create(['user_id' => $user->id]);
        $media->delete(); // Soft delete the media

        // Restore the media
        $media->restore();

        // Assert the media is restored
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
} 