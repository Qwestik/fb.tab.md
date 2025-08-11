<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Post extends Model { protected $fillable=['uuid','status','scheduled_at','published_at','timezone']; protected $casts=['scheduled_at'=>'datetime','published_at'=>'datetime']; }
