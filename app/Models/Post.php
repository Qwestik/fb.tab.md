<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    protected $fillable = ['title','body','status','scheduled_at','published_at'];
    protected $casts = ['scheduled_at'=>'datetime','published_at'=>'datetime'];

    // posts ↔ channels (pivot: post_channels)
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'post_channels')
            ->withPivot(['status','fb_post_id','published_at','error'])
            ->withTimestamps();
    }

    // dacă folosești media locală
    public function media()
    {
        return $this->hasMany(Media::class);
    }
}
