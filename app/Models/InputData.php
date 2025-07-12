<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InputData extends Model
{
    use HasFactory;

    protected $table = 'input_data';

    protected $fillable = [
        'nama_komoditas',
        'jenis_komoditas',
        'lokasi_pasar',
        'satuan',
        'longitude',
        'latitude',
        'harga',
        'tanggal',
        'asal_pemasok',
        'jumlah_permintaan',
        'status',
        'tingkat_pasokan',	
        'user_id',
    ];

    protected $casts = [
        'longitude' => 'decimal:7',
        'latitude' => 'decimal:7',
        'harga' => 'decimal:2',
        'tanggal' => 'date',
    ];

    protected $oldHarga = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function alternatif(): HasOne
    {
        return $this->hasOne(DataAlternatif::class, 'input_data_id');
    }

    protected static function boot()
    {
        parent::boot();

        // Menangkap nilai harga lama sebelum update
        static::updating(function ($model) {
            if ($model->isDirty('harga')) {
                $model->oldHarga = $model->getOriginal('harga');
                Log::info("Menangkap harga lama: {$model->oldHarga} sebelum update ke {$model->harga}");
            }
        });

        // Event saat model berhasil disimpan (setelah data masuk ke database)
        static::created(function ($model) {
            Log::info("InputData baru dibuat dengan ID: {$model->id}, harga: {$model->harga}");
            $model->updateDataAlternatif(); // Perbarui DataAlternatif setelah data baru dibuat
            
            // Propagasi perubahan ke record lain jika diperlukan
            self::propagateChanges($model->tanggal, $model->nama_komoditas, $model->lokasi_pasar);
        });

        // Event saat model diperbarui - PERBAIKAN UTAMA DI SINI
        static::updated(function ($model) {
            Log::info("InputData dengan ID: {$model->id} diperbarui");
            
            // PERBAIKAN: Pastikan hanya satu thread yang menjalankan update
            DB::transaction(function() use ($model) {
                $needsUpdate = false;
                $needsPropagation = false;
                
                // Jika harga berubah
                if ($model->wasChanged('harga')) {
                    Log::info("Harga berubah dari {$model->oldHarga} menjadi {$model->harga}");
                    $needsUpdate = true;
                    $needsPropagation = true;
                }

                // Jika field lain berubah (lokasi_pasar, tanggal, dll)
                if ($model->wasChanged(['asal_pemasok', 'jumlah_permintaan', 'tingkat_pasokan', 'lokasi_pasar', 'tanggal', 'nama_komoditas'])) {
                    Log::info("Field penting berubah: " . implode(', ', array_keys($model->getChanges())));
                    $needsUpdate = true;
                    
                    // Jika lokasi_pasar atau tanggal berubah, perlu propagasi khusus
                    if ($model->wasChanged(['lokasi_pasar', 'tanggal'])) {
                        $needsPropagation = true;
                    }
                }

                if ($needsUpdate) {
                    // PERBAIKAN: Cek apakah DataAlternatif sudah ada sebelum update/create
                    $model->updateOrCreateDataAlternatif();
                    
                    if ($needsPropagation) {
                        // Propagasi perubahan ke semua record terkait
                        self::propagateChanges($model->tanggal, $model->nama_komoditas, $model->lokasi_pasar);
                    }
                }
            });
        });
    }

    /**
     * PERBAIKAN: Method baru untuk update atau create DataAlternatif tanpa duplikasi
     */
    protected function updateOrCreateDataAlternatif()
    {
        $harga_sebelumnya = $this->getHargaSebelumnyaAttribute();
        Log::info("Memperbarui/membuat DataAlternatif untuk input_data_id: {$this->id}, harga: {$this->harga}, harga_sebelumnya: " . ($harga_sebelumnya ?? 'null'));
    
        // PERBAIKAN: Gunakan updateOrCreate untuk mencegah duplikasi
        $alternatif = DataAlternatif::updateOrCreate(
            [
                'input_data_id' => $this->id,
            ],
            [
                'nama_komoditas' => $this->nama_komoditas,
                'asal_pemasok' => $this->asal_pemasok,
                'jumlah_permintaan' => $this->jumlah_permintaan,
                'tingkat_pasokan' => $this->tingkat_pasokan,
                'lokasi_pasar' => $this->lokasi_pasar,
                'tanggal' => $this->tanggal,
                'harga' => $this->harga,
                'harga_sebelumnya' => $harga_sebelumnya,
            ]
        );
        
        Log::info($alternatif->wasRecentlyCreated 
            ? "DataAlternatif baru dibuat dengan ID: {$alternatif->id}" 
            : "DataAlternatif diperbarui dengan ID: {$alternatif->id}");
        
        return $alternatif;
    }

    /**
     * Method lama - dipertahankan untuk backward compatibility
     */
    protected function updateDataAlternatif()
    {
        return $this->updateOrCreateDataAlternatif();
    }

    /**
     * Menghitung harga sebelumnya berdasarkan tanggal sebelumnya (DYNAMIC accessor)
     */
    public function getHargaSebelumnyaAttribute()
    {
        $sebelumnya = self::where('nama_komoditas', $this->nama_komoditas)
            ->where('lokasi_pasar', $this->lokasi_pasar)
            ->where('tanggal', '<', $this->tanggal)
            ->orderBy('tanggal', 'desc')
            ->value('harga');
            
        Log::info("Mendapatkan harga_sebelumnya untuk {$this->nama_komoditas} di {$this->lokasi_pasar} pada {$this->tanggal}: " . ($sebelumnya ?? 'null'));
        
        // Jika ini adalah record pertama, gunakan harga saat ini
        if (is_null($sebelumnya)) {
            Log::info("Tidak ada harga sebelumnya. Menggunakan harga saat ini: {$this->harga}");
            return $this->harga;
        }
        
        return $sebelumnya;
    }

    /**
     * Propagasi perubahan harga ke DataAlternatif
     */
    public static function propagateChanges($tanggal, $nama_komoditas, $lokasi_pasar)
    {
        Log::info("Mempropagasi perubahan untuk {$nama_komoditas} di {$lokasi_pasar} mulai tanggal {$tanggal}");
        
        try {
            DB::transaction(function() use ($tanggal, $nama_komoditas, $lokasi_pasar) {
                // Ambil semua record terkait berurutan berdasarkan tanggal
                $records = DataAlternatif::where('nama_komoditas', $nama_komoditas)
                    ->where('lokasi_pasar', $lokasi_pasar)
                    ->orderBy('tanggal', 'asc')
                    ->get();
                
                if ($records->isEmpty()) {
                    Log::info("Tidak ada record yang perlu diperbarui");
                    return;
                }
                
                Log::info("Menemukan {$records->count()} record untuk propagasi perubahan");
                
                // Perbarui setiap record secara berurutan
                foreach ($records as $index => $record) {
                    if ($index === 0) {
                        // Record pertama: cek apakah ini adalah record pertama untuk komoditas ini
                        $prevRecord = self::where('nama_komoditas', $nama_komoditas)
                            ->where('lokasi_pasar', $lokasi_pasar)
                            ->where('tanggal', '<', $record->tanggal)
                            ->orderBy('tanggal', 'desc')
                            ->first();
                            
                        if ($prevRecord) {
                            // Ada record sebelumnya di tabel InputData
                            $record->harga_sebelumnya = $prevRecord->harga;
                            Log::info("Record pertama: {$record->id} (tanggal: {$record->tanggal}) mendapat harga sebelumnya: {$prevRecord->harga} dari input_data");
                        } else {
                            // Benar-benar record pertama, gunakan harga saat ini
                            $record->harga_sebelumnya = $record->harga;
                            Log::info("Record pertama absolut: {$record->id} menggunakan harga saat ini sebagai harga sebelumnya: {$record->harga}");
                        }
                    } else {
                        // Record berikutnya: gunakan harga dari record sebelumnya
                        $prevRecord = $records[$index-1];
                        $record->harga_sebelumnya = $prevRecord->harga;
                        Log::info("Record: {$record->id} (tanggal: {$record->tanggal}) mendapat harga sebelumnya: {$prevRecord->harga} dari record sebelumnya (tanggal: {$prevRecord->tanggal})");
                    }
                    
                    // Hitung ulang nilai turunan dan simpan
                    $record->calculateAndSaveDerivedValues();
                }
            });
        } catch (\Exception $e) {
            Log::error("Terjadi kesalahan dalam propagasi perubahan: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }
}