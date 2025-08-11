<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Medium extends Model { protected $table='media'; protected $fillable=['uuid','disk','path','mime','size','conversions']; protected $casts=['conversions'=>'array']; }
