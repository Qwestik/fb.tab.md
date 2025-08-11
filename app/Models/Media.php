<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = ['post_id','disk','path','mime','width','height'];

    public function url(): string {
        return Storage::disk($this->disk)->url($this->path);
    }
}
