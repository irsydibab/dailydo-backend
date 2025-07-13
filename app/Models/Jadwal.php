<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hari',
        'jam',
        'jam_selesai',
        'aktivitas',
        'kategori',
        'evaluasi',
        'status',
        'timer_durasi',
        'timer_start',
    ];

    protected $casts = [
        'timer_start' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
