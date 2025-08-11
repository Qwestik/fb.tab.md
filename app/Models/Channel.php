<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
    protected $fillable = ['platform','page_id','name','access_token','token_expires_at','meta'];
    protected $casts = ['meta'=>'array','token_expires_at'=>'datetime'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_channels')
            ->withPivot(['status','fb_post_id','published_at','error'])
            ->withTimestamps();
    }
}
