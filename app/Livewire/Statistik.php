<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\DataAlternatif;
use Livewire\Attributes\On;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Statistik extends Component
{
    public $filters = [
        'nama_komoditas' => null,
        'startDate' => null,
        'endDate' => null,
    ];

    public $chartType = 'line'; // Menambahkan opsi untuk memilih jenis grafik

    public function mount()
    {
        // Inisialisasi filter tanggal default
        $this->filters['startDate'] = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->filters['endDate'] = now()->format('Y-m-d');
    }

    #[On('filters-updated')]
    public function updateFilters($filters)
    {
        $this->filters = $filters;
    }

    public function render()
    {
        // Ambil nilai filter
        $selectedKomoditas = $this->filters['nama_komoditas'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // Query dasar
        $query = DataAlternatif::query();

        // Terapkan filter jika ada
        if ($startDate) {
            $query->whereDate('tanggal', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->whereDate('tanggal', '<=', Carbon::parse($endDate));
        }
        if ($selectedKomoditas) {
            $query->where('nama_komoditas', $selectedKomoditas);
        }

        // Hitung statistik
        $stats = [
            'totalData' => $query->count(),
            'averagePrice' => number_format($query->avg('harga'), 2),
            'uniqueCommodities' => $selectedKomoditas ? 1 : $query->distinct('nama_komoditas')->count('nama_komoditas'),
            'uniqueMarkets' => $query->distinct('lokasi_pasar')->count('lokasi_pasar')
        ];

        // Persiapkan data grafik
        $chartData = $this->prepareHighchartData($query, $selectedKomoditas);

        return view('livewire.statistik', [
            'stats' => $stats,
            'chartConfig' => $chartData, // Kirim konfigurasi grafik
            'isCommoditySelected' => !is_null($selectedKomoditas)
        ]);
    }

    protected function prepareHighchartData($query, $selectedKomoditas)
    {
        if ($selectedKomoditas) {
            // Line chart - Harga Harian Komoditas
            if ($this->chartType == 'line') {
                $data = $query->select('tanggal', 'harga')
                              ->orderBy('tanggal')
                              ->get();

                $categories = $data->pluck('tanggal')->map(fn($date) => Carbon::parse($date)->format('d M Y'))->toArray();
                $seriesData = $data->pluck('harga')->map(fn($price) => (float)$price)->toArray();

                return [
                    'chart' => ['type' => 'line'],
                    'title' => ['text' => "Harga Harian: {$selectedKomoditas}"],
                    'xAxis' => [
                        'categories' => $categories,
                        'labels' => ['rotation' => -45]
                    ],
                    'yAxis' => [
                        'title' => ['text' => 'Harga (Rp)'],
                        'labels' => ['format' => 'Rp {value:,.0f}']
                    ],
                    'tooltip' => [
                        'pointFormat' => '<span style="color:{series.color}">{series.name}</span>: <b>Rp {point.y:,.0f}</b><br/>',
                        'shared' => true
                    ],
                    'series' => [[
                        'name' => $selectedKomoditas,
                        'data' => $seriesData,
                        'color' => '#2196F3'
                    ]],
                    'credits' => ['enabled' => false],
                ];
            }
        } else {
            // Column chart - Rata-rata harga semua komoditas
            if ($this->chartType == 'column') {
                $data = $query->select('nama_komoditas', DB::raw('AVG(harga) as avg_harga'))
                              ->groupBy('nama_komoditas')
                              ->orderByDesc('avg_harga')
                              ->get();

                $seriesData = $data->map(function ($item) {
                    return ['name' => $item->nama_komoditas, 'y' => round((float)$item->avg_harga, 2)];
                })->toArray();

                return [
                    'chart' => ['type' => 'column'],
                    'title' => ['text' => 'Rata-rata Harga Komoditas'],
                    'xAxis' => ['type' => 'category'],
                    'yAxis' => [
                        'title' => ['text' => 'Harga Rata-rata (Rp)'],
                        'labels' => ['format' => 'Rp {value:,.0f}']
                    ],
                    'tooltip' => [
                        'pointFormat' => '<span style="color:{point.color}">{point.name}</span>: <b>Rp {point.y:,.0f}</b><br/>',
                        'shared' => false
                    ],
                    'series' => [[
                        'name' => 'Harga Rata-rata',
                        'data' => $seriesData,
                        'colorByPoint' => true
                    ]],
                    'credits' => ['enabled' => false],
                ];
            }
        }
    }
}
