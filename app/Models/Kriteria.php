<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Kriteria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kriterias'; // Sesuaikan dengan nama tabel di database
    protected $primaryKey = 'id'; // Sesuaikan dengan primary key di migrasi
    public $incrementing = true; // Auto-increment
    protected $keyType = 'int'; // Tipe data primary key

    // Kolom yang bisa diisi secara massal
    protected $fillable = [
        'keterangan',
        'kode_kriteria',
        'bobot',
    ];

    // Relasi ke tabel sub_kriterias
    public function subKriteria()
    {
        return $this->hasMany(SubKriteria::class, 'id_kriteria');
    }

    // Kriteria.php model
public function subKriterias()
{
    return $this->hasMany(SubKriteria::class, 'id_kriteria');
}


    // Reset auto-increment saat tabel kosong
    protected static function boot()
    {
        parent::boot();

        static::deleted(function () {
            if (static::count() === 0) {
                DB::statement('ALTER TABLE kriterias AUTO_INCREMENT = 1');
            }
        });
    }

    
}