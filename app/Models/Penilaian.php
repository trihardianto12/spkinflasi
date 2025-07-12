<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Penilaian extends Model
{
    use HasFactory;

    protected $table = 'penilaian';
    protected $fillable = [
        'alternatif_id', 'nama_komoditas', 'tanggal',
        'asal_pemasok', 'jumlah_permintaan',
        'tingkat_pasokan', 'laju_inflasi', 'perubahan_harga',
    ];

    protected $appends = ['maut_score', 'ranking'];

    public function dataAlternatif(): BelongsTo
    {
        return $this->belongsTo(DataAlternatif::class, 'alternatif_id');
    }

  public function inputData(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InputData::class, 'input_data_id');
    }
  

    /**
     * Scope untuk mengurutkan berdasarkan skor MAUT
     */
   public function scopeOrderByMautScore($query, $direction = 'desc')
{
    return $query->orderBy(
        DB::raw('(
            (COALESCE(laju_inflasi, 0) * (SELECT bobot FROM kriterias WHERE kode_kriteria = "C1" LIMIT 1)) +
            ((SELECT nilai FROM sub_kriteria WHERE id_sub_kriteria = jumlah_permintaan LIMIT 1) * (SELECT bobot FROM kriterias WHERE kode_kriteria = "C2" LIMIT 1)) +
            ((SELECT nilai FROM sub_kriteria WHERE id_sub_kriteria = asal_pemasok LIMIT 1) * (SELECT bobot FROM kriterias WHERE kode_kriteria = "C3" LIMIT 1)) +
            (COALESCE(perubahan_harga, 0) * (SELECT bobot FROM kriterias WHERE kode_kriteria = "C4" LIMIT 1)) +
            ((SELECT nilai FROM sub_kriteria WHERE id_sub_kriteria = tingkat_pasokan LIMIT 1) * (SELECT bobot FROM kriterias WHERE kode_kriteria = "C5" LIMIT 1))
        ) / 100'),
        $direction
    );
}

    /**
     * Accessor untuk skor MAUT
     */
    public function getMautScoreAttribute()
    {
        $weights = Kriteria::whereIn('kode_kriteria', ['C1', 'C2', 'C3', 'C4', 'C5'])
            ->pluck('bobot', 'kode_kriteria')
            ->mapWithKeys(fn($bobot, $kode) => [strtolower($kode) => $bobot / 100])
            ->toArray();

        $normalized = $this->normalizePenilaianValues();
        
        return 
            $normalized['normalizedC1'] * $weights['c1'] +
            $normalized['normalizedC2'] * $weights['c2'] +
            $normalized['normalizedC3'] * $weights['c3'] +
            $normalized['normalizedC4'] * $weights['c4'] +
            $normalized['normalizedC5'] * $weights['c5'];
    }

    /**
     * Accessor untuk ranking
     */
    public function getRankingAttribute()
    {
        // Dapatkan semua ID yang sudah diurutkan berdasarkan skor MAUT
        $rankedIds = self::orderByMautScore('desc')->pluck('id')->toArray();
        
        // Cari posisi ID saat ini dalam array
        $position = array_search($this->id, $rankedIds);
        
        return $position !== false ? $position + 1 : null;
    }

    /**
     * Normalisasi nilai kriteria
     */
    public function normalizePenilaianValues()
    {
        $c1Value = (float)$this->laju_inflasi;
        $c2Value = (float)optional(SubKriteria::find($this->jumlah_permintaan))->nilai ?? 0;
        $c3Value = (float)optional(SubKriteria::find($this->asal_pemasok))->nilai ?? 0;
        $c4Value = (float)$this->perubahan_harga;
        $c5Value = (float)optional(SubKriteria::find($this->tingkat_pasokan))->nilai ?? 0;
        
        $ranges = [
            'c1' => [
                'min' => self::min('laju_inflasi'),
                'max' => self::max('laju_inflasi')
            ],
            'c2' => [
                'min' => SubKriteria::whereIn('id_sub_kriteria', self::pluck('jumlah_permintaan')->unique())
                         ->min('nilai'),
                'max' => SubKriteria::whereIn('id_sub_kriteria', self::pluck('jumlah_permintaan')->unique())
                         ->max('nilai')
            ],
            'c3' => [
                'min' => SubKriteria::whereIn('id_sub_kriteria', self::pluck('asal_pemasok')->unique())
                         ->min('nilai'),
                'max' => SubKriteria::whereIn('id_sub_kriteria', self::pluck('asal_pemasok')->unique())
                         ->max('nilai')
            ],
            'c4' => [
                'min' => self::min('perubahan_harga'),
                'max' => self::max('perubahan_harga')
            ],
            'c5' => [
                'min' => SubKriteria::whereIn('id_sub_kriteria', self::pluck('tingkat_pasokan')->unique())
                         ->min('nilai'),
                'max' => SubKriteria::whereIn('id_sub_kriteria', self::pluck('tingkat_pasokan')->unique())
                         ->max('nilai')
            ]
        ];
        
        return [
            'normalizedC1' => $this->normalizeValue($c1Value, $ranges['c1']['min'], $ranges['c1']['max']),
            'normalizedC2' => $this->normalizeValue($c2Value, $ranges['c2']['min'], $ranges['c2']['max']),
            'normalizedC3' => $this->normalizeValue($c3Value, $ranges['c3']['min'], $ranges['c3']['max']),
            'normalizedC4' => $this->normalizeValue($c4Value, $ranges['c4']['min'], $ranges['c4']['max']),
            'normalizedC5' => $this->normalizeValue($c5Value, $ranges['c5']['min'], $ranges['c5']['max']),
        ];
    }
    
    private function normalizeValue($value, $min, $max)
    {
        if ($max == $min) return 0;
        $normalized = ($value - $min) / ($max - $min);
        return max(0, min(1, $normalized));
    }
}