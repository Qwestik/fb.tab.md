<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $fillable = [
        'platform','page_id','name','access_token','token_expires_at','meta'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function getPlatformLabelAttribute(): string
    {
        return $this->platform === 'ig' ? 'Instagram' : 'Facebook';
    }
}
