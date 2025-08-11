<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiSetting extends Model { protected $fillable=['config']; protected $casts=['config'=>'array']; }
