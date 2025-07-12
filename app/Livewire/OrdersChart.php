<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\DataAlternatif;
use Filament\Notifications\Notification; // Pastikan Filament terinstal jika Anda menggunakan ini

class OrdersChart extends Component
{
    public $filters = [
        'nama_komoditas' => null,
        'startDate' => null,
        'endDate' => null,
    ];

    public $chartData = [
        'datasets' => [],
        'labels' => [],
    ];

    public function mount()
    {
        // Inisialisasi data chart tanpa filter default untuk menampilkan semua data historis
        $this->updateChartData();
    }

    #[On('filters-updated')]
    public function updateFilters($filters)
    {
        // Perbarui filter dan pastikan hanya nilai yang valid yang digunakan
        $this->filters = array_merge($this->filters, array_filter($filters, fn($value) => !is_null($value)));

        // Validasi rentang tanggal
        if ($this->filters['startDate'] && $this->filters['endDate'] && $this->filters['startDate'] > $this->filters['endDate']) {
            Notification::make()
                ->title('Rentang tanggal tidak valid')
                ->body('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.')
                ->danger()
                ->send();
            return;
        }

        $this->updateChartData();
    }

    protected function updateChartData()
    {
        $selectedKomoditas = $this->filters['nama_komoditas'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // Inisialisasi query dasar
        $baseQuery = DataAlternatif::query();

        // Terapkan filter rentang tanggal
        if ($startDate) {
            $baseQuery->whereDate('tanggal', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('tanggal', '<=', $endDate);
        }

        $datasets = [];
        $labels = [];

        // Daftar warna untuk garis chart
        $colors = [
            '#4CAF50', '#2196F3', '#FFC107', '#F44336',
            '#9C27B0', '#FF9800', '#00BCD4', '#607D8B',
        ];
        $colorIndex = 0;

        if ($selectedKomoditas) {
            // Jika komoditas tertentu dipilih
            $data = $baseQuery->clone()
                ->where('nama_komoditas', $selectedKomoditas)
                ->selectRaw('lokasi_pasar, AVG(harga) as average_harga')
                ->groupBy('lokasi_pasar')
                ->get();

            if ($data->isEmpty()) {
                Notification::make()
                    ->title('Data tidak tersedia')
                    ->body('Tidak ada data untuk komoditas dan rentang tanggal yang dipilih.')
                    ->warning()
                    ->send();
                $this->chartData = ['datasets' => [], 'labels' => []];
                // Kirim event meskipun data kosong untuk membersihkan chart
                $this->dispatch('chartDataUpdated', chartData: $this->chartData, chartOptions: $this->getChartOptions());
                return;
            }

            $labels = $data->pluck('lokasi_pasar')->toArray();
            $prices = $data->pluck('average_harga')->toArray();

            $datasets[] = [
                'label' => "Harga Rata-rata {$selectedKomoditas}",
                'data' => $prices,
                'fill' => 'start',
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => 'rgba(76, 175, 80, 0.2)',
                'tension' => 0.4,
            ];
        } else {
            // Jika tidak ada komoditas yang dipilih, tampilkan semua komoditas
            $allData = $baseQuery->get();
            if ($allData->isEmpty()) {
                Notification::make()
                    ->title('Data tidak tersedia')
                    ->body('Tidak ada data untuk rentang tanggal yang dipilih.')
                    ->warning()
                    ->send();
                $this->chartData = ['datasets' => [], 'labels' => []];
                // Kirim event meskipun data kosong untuk membersihkan chart
                $this->dispatch('chartDataUpdated', chartData: $this->chartData, chartOptions: $this->getChartOptions());
                return;
            }

            $uniqueKomoditas = $allData->pluck('nama_komoditas')->unique()->toArray();
            $allLokasiPasar = $allData->pluck('lokasi_pasar')->unique()->toArray();

            sort($allLokasiPasar);
            $labels = $allLokasiPasar;

            foreach ($uniqueKomoditas as $komoditas) {
                $commodityData = $allData->where('nama_komoditas', $komoditas);
                $groupedData = $commodityData->groupBy('lokasi_pasar')->map(function ($items) {
                    return $items->avg('harga');
                });

                $pricesForKomoditas = [];
                foreach ($labels as $lokasiPasar) {
                    $pricesForKomoditas[] = $groupedData->get($lokasiPasar);
                }

                $datasets[] = [
                    'label' => $komoditas,
                    'data' => $pricesForKomoditas,
                    'fill' => false,
                    'borderColor' => $colors[$colorIndex % count($colors)],
                    'backgroundColor' => $colors[$colorIndex % count($colors)] . '20',
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'pointBackgroundColor' => $colors[$colorIndex % count($colors)],
                ];
                $colorIndex++;
            }
        }

        // Perbarui data chart
        $this->chartData = [
            'datasets' => $datasets,
            'labels' => $labels,
        ];

        // Kirim event untuk memperbarui chart di frontend
        $this->dispatch('chartDataUpdated', chartData: $this->chartData, chartOptions: $this->getChartOptions());
    }

    protected function getChartOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Harga (IDR)',
                    ],
                    'ticks' => [
                        // Teruskan callback sebagai string untuk dikonversi di JS
                        'callback' => 'function(value) { return "Rp " + value.toLocaleString("id-ID"); }',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Lokasi Pasar',
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        // Teruskan callback sebagai string untuk dikonversi di JS
                        'label' => 'function(context) { return context.dataset.label + ": Rp " + context.parsed.y.toLocaleString("id-ID"); }',
                    ],
                ],
                'legend' => [
                    'display' => true,
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    public function render()
    {
        return view('livewire.orders-chart', [
            'chartData' => $this->chartData,
            'chartOptions' => $this->getChartOptions(),
            'heading' => 'Tingkat Kenaikan Komoditas',
        ]);
    }
}
