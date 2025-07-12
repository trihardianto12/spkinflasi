<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HasilAkhir;
use App\Models\Penilaian;
use App\Models\DataAlternatif;
use Illuminate\Support\Facades\DB;

class TabelHasilAkhir extends Component
{
    // Properti untuk menampung nilai dari filter
    public ?string $tanggal_mulai = '';
    public ?string $tanggal_akhir = '';
    public ?string $nama_komoditas = '';
    public ?string $lokasi_pasar = '';

    // Properti untuk data yang akan dikirim ke view
    public $komoditasOptions = [];
    public $lokasiOptions = [];

    // Method ini berjalan saat komponen pertama kali dimuat
    public function mount()
    {
        // Mengisi data awal untuk dropdown filter
        $this->komoditasOptions = Penilaian::distinct()->pluck('nama_komoditas');
        $this->lokasiOptions = DataAlternatif::distinct()->pluck('lokasi_pasar');
    }

    // Method ini berjalan setiap kali ada pembaruan pada komponen
    public function render()
    {
        // 1. Memulai query dasar
        $query = HasilAkhir::query();

        // 2. Menerapkan filter berdasarkan properti
        if ($this->tanggal_mulai) {
            $query->whereDate('tanggal', '>=', $this->tanggal_mulai);
        }
        if ($this->tanggal_akhir) {
            $query->whereDate('tanggal', '<=', $this->tanggal_akhir);
        }
        if ($this->nama_komoditas) {
            $query->where('nama_komoditas', $this->nama_komoditas);
        }
        if ($this->lokasi_pasar) {
            $query->whereHas('dataAlternatif', function ($q) {
                $q->where('lokasi_pasar', $this->lokasi_pasar);
            });
        }

        // 3. Logika inti: Tampilkan data terbaru HANYA jika filter tanggal tidak aktif
        $hasDateFilter = $this->tanggal_mulai || $this->tanggal_akhir;
        if (!$hasDateFilter) {
            $query->latestPerCommodityAndLocation();
        }

        // 4. Ambil semua data yang sudah difilter
        $records = $query->get();

        // 5. Hitung skor MAUT, urutkan, dan berikan peringkat
        $rankedRecords = $records
            ->map(function ($record) {
                $record->calculated_maut_score = $record->maut_score;
                return $record;
            })
            ->sortByDesc('calculated_maut_score')
            ->values()
            ->map(function ($record, $key) {
                $record->ranking = $key + 1;
                return $record;
            });

        // 6. Kirim data yang sudah siap ke view
        return view('livewire.tabel-hasil-akhir', [
            'records' => $rankedRecords
        ]);
    }
}