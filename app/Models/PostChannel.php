<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostChannel extends Model
{
    protected $table = 'post_channels';
	
    protected $guarded = [];
    
    protected $fillable = [
        'post_id','channel_id','provider_post_id','status','last_error',
    ];

   protected $casts = ['published_at'=>'datetime'];

    public function channel()
    {
        return $this->belongsTo(\App\Models\Channel::class);
    }

    public function post()
    {
        return $this->belongsTo(\App\Models\Post::class);
    }
}
