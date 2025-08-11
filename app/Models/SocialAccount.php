<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SocialAccount extends Model { protected $fillable=['provider','page_id','name','access_token','config']; protected $casts=['config'=>'array']; }
