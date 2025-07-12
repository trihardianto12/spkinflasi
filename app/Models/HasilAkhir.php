<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class HasilAkhir extends Model
{
    use HasFactory;

    protected $table = 'penilaian'; //
    protected $fillable = [
        'alternatif_id', 'nama_komoditas', 'tanggal',
        'asal_pemasok', 'jumlah_permintaan',
        'tingkat_pasokan', 'laju_inflasi', 'perubahan_harga',
    ]; //
    protected $appends = ['maut_score', 'ranking']; //

    public function dataAlternatif(): BelongsTo
    {
        return $this->belongsTo(DataAlternatif::class, 'alternatif_id'); //
    }

    public function inputData(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InputData::class, 'input_data_id'); //
    }

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
        ); //
    }

    public function scopeLatestPerCommodityAndLocation(Builder $query): Builder
    {
        $latestRecords = DB::table('penilaian as p1')
            ->join('data_alternatif as da1', 'p1.alternatif_id', '=', 'da1.id')
            ->select('p1.id')
            ->whereRaw('p1.tanggal = (
                SELECT MAX(p2.tanggal) 
                FROM penilaian p2 
                JOIN data_alternatif da2 ON p2.alternatif_id = da2.id
                WHERE p2.nama_komoditas = p1.nama_komoditas 
                AND da2.lokasi_pasar = da1.lokasi_pasar
            )')
            ->pluck('id'); //

        return $query->whereIn('id', $latestRecords); //
    }

    public static function applyFiltersToQuery(Builder $query, array $filters = []): Builder
    {
        if (!empty($filters['tanggal_mulai'])) {
            $query->whereDate('tanggal', '>=', $filters['tanggal_mulai']); //
        }
        if (!empty($filters['tanggal_akhir'])) {
            $query->whereDate('tanggal', '<=', $filters['tanggal_akhir']); //
        }
        if (!empty($filters['nama_komoditas'])) {
            $query->where('nama_komoditas', $filters['nama_komoditas']); //
        }
        if (!empty($filters['lokasi_pasar'])) {
            $query->whereHas('dataAlternatif', function ($q) use ($filters) {
                $q->where('lokasi_pasar', $filters['lokasi_pasar']);
            }); //
        }

        return $query;
    }

    private function getContextualQuery(): Builder
    {
        $query = self::query();
        $filters = $this->getCurrentFilters();

        $query = self::applyFiltersToQuery($query, $filters);

        $hasDateFilter = !empty($filters['tanggal_mulai']) || !empty($filters['tanggal_akhir']);

        if (!$hasDateFilter) {
            $query->latestPerCommodityAndLocation();
        }

        return $query;
    }

    public function getMautScoreAttribute(): float
    {
        $weights = \App\Models\Kriteria::whereIn('kode_kriteria', ['C1', 'C2', 'C3', 'C4', 'C5'])
            ->pluck('bobot', 'kode_kriteria')
            ->mapWithKeys(fn($bobot, $kode) => [strtolower($kode) => $bobot / 100])
            ->toArray(); //

        $normalized = $this->normalizePenilaianValues(); //

        return 
            ($normalized['normalizedC1'] * ($weights['c1'] ?? 0)) +
            ($normalized['normalizedC2'] * ($weights['c2'] ?? 0)) +
            ($normalized['normalizedC3'] * ($weights['c3'] ?? 0)) +
            ($normalized['normalizedC4'] * ($weights['c4'] ?? 0)) +
            ($normalized['normalizedC5'] * ($weights['c5'] ?? 0));
    }

    public function getRankingAttribute(): ?int
    {
        $query = $this->getContextualQuery();
        
        $rankedIds = $query->orderByMautScore('desc')->pluck('id')->toArray();
        
        $position = array_search($this->id, $rankedIds);

        return $position !== false ? $position + 1 : null;
    }

    private function getCurrentFilters(): array
    {
        $filters = [];
        $requestFilters = request()->get('tableFilters', []); //
        
        if (request()->has('filters')) {
            $requestFilters = array_merge($requestFilters, json_decode(request()->get('filters', '[]'), true));
        }

        if (!empty($requestFilters['tanggal']['tanggal_mulai'])) {
            $filters['tanggal_mulai'] = $requestFilters['tanggal']['tanggal_mulai'];
        }
        if (!empty($requestFilters['tanggal']['tanggal_akhir'])) {
            $filters['tanggal_akhir'] = $requestFilters['tanggal']['tanggal_akhir'];
        }
        if (!empty($requestFilters['nama_komoditas'])) {
            $filters['nama_komoditas'] = $requestFilters['nama_komoditas'];
        }
        if (!empty($requestFilters['lokasi_pasar'])) {
            $filters['lokasi_pasar'] = $requestFilters['lokasi_pasar'];
        }

        return $filters;
    }

    public function normalizePenilaianValues(): array
    {
        $c1Value = (float)$this->laju_inflasi;
        $c2Value = (float)(\App\Models\SubKriteria::find($this->jumlah_permintaan)?->nilai ?? 0);
        $c3Value = (float)(\App\Models\SubKriteria::find($this->asal_pemasok)?->nilai ?? 0);
        $c4Value = (float)$this->perubahan_harga;
        $c5Value = (float)(\App\Models\SubKriteria::find($this->tingkat_pasokan)?->nilai ?? 0); //

        $contextualQuery = $this->getContextualQuery();

        $ranges = [
            'c1' => ['min' => (clone $contextualQuery)->min('laju_inflasi'), 'max' => (clone $contextualQuery)->max('laju_inflasi') ?: 1],
            'c2' => ['min' => \App\Models\SubKriteria::whereIn('id_sub_kriteria', (clone $contextualQuery)->pluck('jumlah_permintaan'))->min('nilai') ?: 0, 'max' => \App\Models\SubKriteria::whereIn('id_sub_kriteria', (clone $contextualQuery)->pluck('jumlah_permintaan'))->max('nilai') ?: 1],
            'c3' => ['min' => \App\Models\SubKriteria::whereIn('id_sub_kriteria', (clone $contextualQuery)->pluck('asal_pemasok'))->min('nilai') ?: 0, 'max' => \App\Models\SubKriteria::whereIn('id_sub_kriteria', (clone $contextualQuery)->pluck('asal_pemasok'))->max('nilai') ?: 1],
            'c4' => ['min' => (clone $contextualQuery)->min('perubahan_harga'), 'max' => (clone $contextualQuery)->max('perubahan_harga') ?: 1],
            'c5' => ['min' => \App\Models\SubKriteria::whereIn('id_sub_kriteria', (clone $contextualQuery)->pluck('tingkat_pasokan'))->min('nilai') ?: 0, 'max' => \App\Models\SubKriteria::whereIn('id_sub_kriteria', (clone $contextualQuery)->pluck('tingkat_pasokan'))->max('nilai') ?: 1],
        ]; //

        return [
            'normalizedC1' => $this->normalizeValue($c1Value, $ranges['c1']['min'], $ranges['c1']['max']),
            'normalizedC2' => $this->normalizeValue($c2Value, $ranges['c2']['min'], $ranges['c2']['max']),
            'normalizedC3' => $this->normalizeValue($c3Value, $ranges['c3']['min'], $ranges['c3']['max']),
            'normalizedC4' => $this->normalizeValue($c4Value, $ranges['c4']['min'], $ranges['c4']['max']),
            'normalizedC5' => $this->normalizeValue($c5Value, $ranges['c5']['min'], $ranges['c5']['max']),
        ]; //
    }

    private function normalizeValue($value, $min, $max): float
    {
        if ($max == $min) return 0; //
        
        return max(0, min(1, ($value - $min) / ($max - $min))); //
    }
}