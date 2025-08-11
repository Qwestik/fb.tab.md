<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PostTarget extends Model { protected $fillable=['post_id','account_id','status','provider_post_id','errors']; protected $casts=['errors'=>'array']; }
