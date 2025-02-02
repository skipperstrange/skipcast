<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelMedia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'channel_id',
        'media_id',
        'active',
        'list_order',
    ];
} 