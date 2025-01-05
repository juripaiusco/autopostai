<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'child_on',
        'child_max',
        'channel_facebook_on',
        'channel_facebook_reply_on',
        'channel_facebook_reply_n',
        'channel_instagram_on',
        'channel_instagram_reply_on',
        'channel_instagram_reply_n',
        'channel_wordpress_on',
        'channel_wordpress_reply_on',
        'channel_wordpress_reply_n',
        'channel_newsletter_on',
        'channel_newsletter_reply_on',
        'channel_newsletter_reply_n',
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
}
