<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;  // Uncomment to enable email verification
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable // implements MustVerifyEmail  // Uncomment to enable email verification
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'avatar',
        'provider',
        'provider_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    /**
     * Check if user has reached the free channel limit
     */
    public function hasReachedChannelLimit(): bool
    {
        return !$this->isPremium() && $this->channels()->count() >= 5;
    }

    /**
     * Check if user is a premium subscriber
     */
    public function isPremium(): bool
    {
        // TODO: Implement subscription check
        return false;
    }

    /*
    // Email verification routes - Uncomment when needed
    Route::middleware(['auth:sanctum', 'signed'])->group(function () {
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    });

    // Protected routes requiring verified email
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        // Add routes that require verified email here
    });
    */
}
