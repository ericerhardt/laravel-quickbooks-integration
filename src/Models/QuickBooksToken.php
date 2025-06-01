<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class QuickBooksToken extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quickbooks_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'realm_id',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'company_name',
        'company_email',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns the QuickBooks token.
     */
    public function user(): BelongsTo
    {
        $userModel = config('quickbooks.database.users_table', 'users');
        $userClass = config('auth.providers.users.model', 'App\\Models\\User');
        
        return $this->belongsTo($userClass);
    }

    /**
     * Check if the access token is expired.
     *
     * @return bool
     */
    public function isAccessTokenExpired(): bool
    {
        return $this->access_token_expires_at <= now();
    }

    /**
     * Check if the refresh token is expired.
     *
     * @return bool
     */
    public function isRefreshTokenExpired(): bool
    {
        return $this->refresh_token_expires_at <= now();
    }

    /**
     * Check if the token is valid (not expired and active).
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isAccessTokenExpired();
    }

    /**
     * Check if the token can be refreshed.
     *
     * @return bool
     */
    public function canBeRefreshed(): bool
    {
        return $this->is_active && !$this->isRefreshTokenExpired();
    }

    /**
     * Scope a query to only include active tokens.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include valid tokens.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->active()
                    ->where('access_token_expires_at', '>', now());
    }

    /**
     * Scope a query to only include expired tokens.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('access_token_expires_at', '<=', now());
    }

    /**
     * Scope a query to only include tokens that can be refreshed.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRefreshable($query)
    {
        return $query->active()
                    ->where('refresh_token_expires_at', '>', now());
    }

    /**
     * Get the decrypted access token.
     *
     * @return string|null
     */
    public function getDecryptedAccessToken(): ?string
    {
        if (!$this->access_token) {
            return null;
        }

        try {
            return config('quickbooks.tokens.encryption', true) 
                ? Crypt::decryptString($this->access_token)
                : $this->access_token;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the decrypted refresh token.
     *
     * @return string|null
     */
    public function getDecryptedRefreshToken(): ?string
    {
        if (!$this->refresh_token) {
            return null;
        }

        try {
            return config('quickbooks.tokens.encryption', true) 
                ? Crypt::decryptString($this->refresh_token)
                : $this->refresh_token;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Deactivate the token.
     *
     * @return bool
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate the token.
     *
     * @return bool
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Update token information.
     *
     * @param array $tokenData
     * @return bool
     */
    public function updateTokens(array $tokenData): bool
    {
        return $this->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $this->refresh_token,
            'access_token_expires_at' => $tokenData['access_token_expires_at'],
            'refresh_token_expires_at' => $tokenData['refresh_token_expires_at'] ?? $this->refresh_token_expires_at,
            'is_active' => true,
        ]);
    }
}

