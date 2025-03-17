<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikConfig extends Model
{
    use HasFactory ;

    protected $table = 'mikrotik_config';

    protected $fillable = [
        'host',
        'user',
        'pass',
        'port',
    ];
}
