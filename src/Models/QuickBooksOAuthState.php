<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuickBooksOAuthState extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quickbooks_oauth_states';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'state_token',
        'user_id',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the OAuth state.
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        return $this->belongsTo($userModel);
    }

    /**
     * Check if the OAuth state is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Generate a new OAuth state for a user.
     */
    public static function createForUser(int $userId, int $expirationMinutes = 60): self
    {
        // Clean up any existing states for this user
        static::where('user_id', $userId)->delete();

        return static::create([
            'state_token' => Str::random(32),
            'user_id' => $userId,
            'expires_at' => now()->addMinutes($expirationMinutes),
        ]);
    }

    /**
     * Find and validate an OAuth state by token.
     */
    public static function findValidState(string $stateToken): ?self
    {
        return static::where('state_token', $stateToken)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Clean up expired OAuth states.
     */
    public static function cleanup(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * Consume the OAuth state (delete it after use).
     */
    public function consume(): bool
    {
        return $this->delete();
    }
}

