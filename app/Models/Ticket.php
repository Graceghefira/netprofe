<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'tickets';

    // Kolom yang dapat diisi
    protected $fillable = [
        'tracking_id',
        'hari_masuk',
        'waktu_masuk',
        'hari_respon',
        'waktu_respon',
        'nama_admin',
        'email',
        'category',
        'priority',
        'status',
        'subject',
        'detail_kendala',
        'owner',
        'time_worked',
        'due_date',
        'kategori_masalah',
        'respon_diberikan',
    ];
}
