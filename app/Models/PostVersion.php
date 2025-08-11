<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PostVersion extends Model { protected $fillable=['post_id','account_id','body','media']; protected $casts=['media'=>'array']; }
