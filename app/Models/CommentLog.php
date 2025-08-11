<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentLog extends Model
{
    protected $fillable = [
        'channel_id','post_id','post_channel_id',
        'page_id','post_fb_id','comment_id','from_id',
        'message','reply','reply_id','status','meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
