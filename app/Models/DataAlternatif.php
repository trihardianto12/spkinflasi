<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataAlternatif extends Model
{
    use HasFactory;

    protected $table = 'data_alternatif';
    protected $fillable = [
        'input_data_id',
        'nama_komoditas',
        'harga',
        'tanggal',
        'lokasi_pasar',
        'harga_sebelumnya',
        'asal_pemasok',
        'jumlah_permintaan',
        'selisih_harga',
        'persentase_perbedaan',
        'perbedaan_harga_tertata',
        'laju_inflasi',
        'perubahan_harga',
        'tingkat_pasokan',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'harga_sebelumnya' => 'decimal:2',
        'tanggal' => 'date',
        'persentase_perbedaan' => 'decimal:2',
        'selisih_harga' => 'decimal:2'
    ];

    // Untuk mencegah infinite loop saat update
    private static $isUpdating = false;

    /**
     * Relasi ke InputData
     */
    public function inputData(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InputData::class, 'input_data_id');
    }

    /**
     * Relasi one-to-many ke Penilaian
     */
    public function penilaians()
    {
        return $this->hasMany(\App\Models\Penilaian::class, 'alternatif_id');
    }

    /**
     * Relasi one-to-one ke Penilaian
     */
    public function penilaian(): HasOne
    {
        return $this->hasOne(\App\Models\Penilaian::class, 'alternatif_id');
    }

    /**
     * Boot method - event listener untuk creating, created, updating, updated
     */
    protected static function boot()
    {
        parent::boot();

        // Mencegah duplikasi saat membuat data alternatif
        static::creating(function ($model) {
            if (self::where('input_data_id', $model->input_data_id)->exists()) {
                Log::warning("Mencoba membuat DataAlternatif duplikat untuk input_data_id: {$model->input_data_id}");
                return false;
            }

            if (is_null($model->harga_sebelumnya)) {
                $model->harga_sebelumnya = self::getPreviousPrice($model);
                if (is_null($model->harga_sebelumnya)) {
                    $model->harga_sebelumnya = $model->harga;
                    Log::info("Record pertama ditemukan untuk {$model->nama_komoditas} di {$model->lokasi_pasar}, menggunakan harga saat ini.");
                }
            }

            $model->calculateAndSaveDerivedValues();
        });

        // Menyimpan harga lama sebelum update
        static::updating(function ($model) {
            if ($model->isDirty('harga')) {
                $model->setAttribute('_oldPrice', $model->getOriginal('harga'));
                Log::info("Menangkap harga lama: {$model->_oldPrice} sebelum update ke {$model->harga}");
            }
        });

        // Setelah record baru dibuat, buat penilaian jika belum ada
        static::created(function ($model) {
            Log::info("DataAlternatif baru dibuat dengan ID: {$model->id}, harga: {$model->harga}");
            $model->createPenilaianIfNotExists();
        });

        // Setelah record diperbarui, perbarui nilai terkait dan penilaian
        static::updated(function ($model) {
            if (self::$isUpdating) return;

            Log::info("DataAlternatif dengan ID: {$model->id} diperbarui");

            DB::transaction(function () use ($model) {
                self::$isUpdating = true;

                try {
                    $needsPenilaianUpdate = false;
                    $needsSubsequentUpdate = false;

                    if ($model->wasChanged('harga') || isset($model->_oldPrice)) {
                        $oldPrice = $model->_oldPrice ?? $model->getOriginal('harga');
                        Log::info("Harga berubah dari {$oldPrice} menjadi {$model->harga}. Memperbarui record berikutnya.");
                        $needsSubsequentUpdate = true;
                        $needsPenilaianUpdate = true;
                        if (isset($model->_oldPrice)) {
                            $model->offsetUnset('_oldPrice');
                        }
                    }

                    if ($model->wasChanged(['asal_pemasok', 'jumlah_permintaan', 'tingkat_pasokan', 'laju_inflasi', 'lokasi_pasar', 'tanggal', 'nama_komoditas'])) {
                        $needsPenilaianUpdate = true;

                        if ($model->wasChanged(['lokasi_pasar', 'tanggal'])) {
                            Log::info("Lokasi pasar atau tanggal berubah, menghitung ulang nilai turunan");
                            $model->harga_sebelumnya = self::getPreviousPrice($model);
                            if (is_null($model->harga_sebelumnya)) {
                                $model->harga_sebelumnya = $model->harga;
                            }
                            $needsSubsequentUpdate = true;
                        }
                    }

                    if ($model->wasChanged('harga') || $model->wasChanged(['lokasi_pasar', 'tanggal'])) {
                        $model->calculateAndSaveDerivedValues();
                    }

                    if ($needsSubsequentUpdate) {
                        $oldPrice = $model->_oldPrice ?? $model->getOriginal('harga');
                        self::updateSubsequentRecords($model, $oldPrice);
                    }

                    if ($needsPenilaianUpdate) {
                        $model->updateOrCreatePenilaian();
                    }

                } finally {
                    self::$isUpdating = false;
                }
            });
        });
    }

    /**
     * Menghitung dan menyimpan nilai-nilai turunan seperti selisih harga, persentase, dll.
     */
    public function calculateAndSaveDerivedValues()
    {
        $this->selisih_harga = $this->calculateSelisihHarga();
        $this->persentase_perbedaan = $this->calculatePersentasePerbedaan();
        $this->perbedaan_harga_tertata = $this->calculatePerbedaanHargaTertata();
        $this->perubahan_harga = $this->calculatePerubahanHarga();
        $this->laju_inflasi = $this->calculateLajuInflasi();

        Log::info("Menghitung nilai turunan untuk ID: {$this->id}, selisih: {$this->selisih_harga}, persentase: {$this->persentase_perbedaan}");

        if ($this->exists) {
            $this->saveQuietly();
        }
    }

    /**
     * Menghitung selisih harga
     */
    protected function calculateSelisihHarga()
    {
        return is_null($this->harga_sebelumnya) ? null : $this->harga - $this->harga_sebelumnya;
    }

    /**
     * Menghitung persentase perubahan harga
     */
    protected function calculatePersentasePerbedaan()
    {
        if (is_null($this->harga_sebelumnya)) return null;
        return $this->harga_sebelumnya == 0 ? 0 : (($this->harga - $this->harga_sebelumnya) / $this->harga_sebelumnya) * 100;
    }

    /**
     * Format perubahan harga dalam bentuk ↑ atau ↓
     */
    protected function calculatePerbedaanHargaTertata()
    {
        $persentase = $this->persentase_perbedaan;
        if (is_null($persentase)) return null;

        $formatted = number_format(abs($persentase), 2);
        return $persentase >= 0 ? "↑ {$formatted}%" : "↓ {$formatted}%";
    }

    /**
     * Kategorisasi perubahan harga (misalnya 1, 2, 3, 4, 5)
     */
    protected function calculatePerubahanHarga()
    {
        $selisih = $this->selisih_harga;
        if (is_null($selisih)) return null;

        if ($selisih < 0) return '1'; // Harga turun
        if ($selisih >= 3000) return '5';
        if ($selisih >= 1000) return '4';
        if ($selisih >= 500) return '3';
        if ($selisih >= 100) return '2';
        return '1';
    }

    /**
     * Hitung laju inflasi berdasarkan persentase perubahan
     */
    protected function calculateLajuInflasi()
    {
        $persentase = $this->persentase_perbedaan;
        if (is_null($persentase)) return null;

        if ($persentase >= 100) return '5';
        if ($persentase >= 50) return '4';
        if ($persentase >= 10) return '3';
        if ($persentase >= -10) return '2';
        return '1';
    }

    /**
     * Mendapatkan harga sebelumnya berdasarkan tanggal
     */
    public static function getPreviousPrice($model)
    {
        $prevPrice = self::where('nama_komoditas', $model->nama_komoditas)
            ->where('lokasi_pasar', $model->lokasi_pasar)
            ->whereDate('tanggal', '<', $model->tanggal)
            ->orderBy('tanggal', 'desc')
            ->value('harga');

        if (is_null($prevPrice)) {
            $prevPrice = \App\Models\InputData::where('nama_komoditas', $model->nama_komoditas)
                ->where('lokasi_pasar', $model->lokasi_pasar)
                ->whereDate('tanggal', '<', $model->tanggal)
                ->orderBy('tanggal', 'desc')
                ->value('harga');
        }

        Log::info("Harga sebelumnya untuk {$model->nama_komoditas} di {$model->lokasi_pasar} pada {$model->tanggal}: " . ($prevPrice ?? 'null'));

        return $prevPrice;
    }

    /**
     * Update semua record setelah ada perubahan harga
     */
    public static function updateSubsequentRecords($model, $oldPrice)
    {
        try {
            DB::transaction(function () use ($model, $oldPrice) {
                Log::info("Memulai pembaruan record berikutnya untuk {$model->nama_komoditas} di {$model->lokasi_pasar} tanggal {$model->tanggal}");

                $nextRecords = self::where('nama_komoditas', $model->nama_komoditas)
                    ->where('lokasi_pasar', $model->lokasi_pasar)
                    ->whereDate('tanggal', '>', $model->tanggal)
                    ->orderBy('tanggal', 'asc')
                    ->get();

                Log::info("Ditemukan {$nextRecords->count()} record untuk diperbarui.");

                foreach ($nextRecords as $nextRecord) {
                    $prevRecord = self::where('nama_komoditas', $model->nama_komoditas)
                        ->where('lokasi_pasar', $model->lokasi_pasar)
                        ->whereDate('tanggal', '<', $nextRecord->tanggal)
                        ->orderBy('tanggal', 'desc')
                        ->first();

                    if ($prevRecord) {
                        $nextRecord->harga_sebelumnya = $prevRecord->harga;
                        $nextRecord->calculateAndSaveDerivedValues();
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error("Error saat memperbarui record berikutnya: " . $e->getMessage());
        }
    }

    /**
     * Menyimpan model tanpa memicu event
     */
    public function saveQuietly(array $options = [])
    {
        return static::withoutEvents(fn() => $this->save($options));
    }

    /**
     * Membuat penilaian hanya jika belum ada
     */
    protected function createPenilaianIfNotExists()
    {
        if (Penilaian::where('alternatif_id', $this->id)->exists()) {
            Log::info("Penilaian sudah ada untuk alternatif_id: {$this->id}, skip pembuatan");
            return;
        }

        $this->updateOrCreatePenilaian();
    }

    /**
     * Update atau create penilaian tanpa duplikasi
     */
    protected function updateOrCreatePenilaian()
    {
        Log::info("Memperbarui/membuat Penilaian untuk alternatif_id: {$this->id}");

        try {
            $penilaian = \App\Models\Penilaian::updateOrCreate(
                ['alternatif_id' => $this->id],
                [
                    'nama_komoditas' => $this->nama_komoditas,
                    'tanggal' => $this->tanggal,
                    'asal_pemasok' => $this->asal_pemasok,
                    'jumlah_permintaan' => $this->jumlah_permintaan,
                    'tingkat_pasokan' => $this->tingkat_pasokan,
                    'perubahan_harga' => $this->perubahan_harga,
                    'laju_inflasi' => $this->laju_inflasi,
                ]
            );

            Log::info($penilaian->wasRecentlyCreated 
                ? "Penilaian baru dibuat dengan ID: {$penilaian->id}" 
                : "Penilaian diperbarui dengan ID: {$penilaian->id}");

            return $penilaian;

        } catch (\Exception $e) {
            Log::error("Error dalam updateOrCreatePenilaian: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Backward compatibility
     */
    protected function updatePenilaian()
    {
        return $this->updateOrCreatePenilaian();
    }
}