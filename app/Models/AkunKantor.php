<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AkunKantor extends Model
{
    use HasFactory;

    // Tentukan tabel yang digunakan oleh model
    protected $table = 'akun_kantor';

    // Tentukan kolom yang dapat diisi (fillable)
    protected $fillable = ['no_hp', 'name', 'profile'];

    // Secara otomatis akan menggunakan timestamps created_at dan updated_at
}
