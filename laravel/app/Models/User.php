<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'parent_id', 'child_on', 'child_max', 'tokens_limit', 'image_model_limit'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'channels' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(Settings::class);
    }

    public function tokensUsed(): HasMany
    {
        return $this->hasMany(TokenLog::class)
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }

    public function imagesUsed(): HasMany
    {
        return $this->hasMany(ImageJob::class)
            ->whereDay('image_jobs.created_at', now()->day);
    }

    public function postLogs(): HasMany
    {
        return $this->hasMany(TokenLog::class)
            ->where('type', 'post')
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }

    public function replyLogs(): HasMany
    {
        return $this->hasMany(TokenLog::class)
            ->where('type', 'reply')
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }
}
