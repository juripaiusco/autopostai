<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'child_on',
        'child_max',
        'tokens_limit',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
//        'password',
        'remember_token',
    ];

    protected static function booted()
    {
        static::deleting(function ($user) {

            $user->posts()->each(function ($post) {

                if ($post->img) {
                    Storage::disk('public')->deleteDirectory('posts/' . $post->id);
                }
            });
        });
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(Settings::class, 'user_id', 'id');
    }

    public function tokens_used()
    {
        return $this->hasOne(Token_log::class, 'user_id')
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }

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
}
