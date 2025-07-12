<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubKriteria extends Model
{
    use HasFactory;

    protected $table = 'sub_kriteria';
    protected $primaryKey = 'id_sub_kriteria';
    
    // Sudah benar menggunakan incrementing dan keyType
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_kriteria',
        'deskripsi',
        'nilai',
    ];

    // Perbaikan pada relasi belongsTo
    public function kriteria()
    {
        return $this->belongsTo(Kriteria::class, 'id_kriteria', 'id');
    }

    
}