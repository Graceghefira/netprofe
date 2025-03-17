<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AkunKantor extends Model
{
    use HasFactory;

    protected $table = 'akun_kantor';

    protected $fillable = ['no_hp', 'name', 'profile'];
}
