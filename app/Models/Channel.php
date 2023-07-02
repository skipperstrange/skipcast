<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    function user(){
        return $this->belongsTo(User::class);
    }

    function reviews(){
         return $this->hasMany(Review::class);
    }

    function review(){

        return $this->hasMany(Review::class);
    }

    function media(){
        return $this->belongsToMany(Media::class, 'channel_media', 'channel_id');
    }

    function genre(){
        return $this->hasMany(Genre::class, 'channel_genres', 'genre_id');
    }

}
