<?php

namespace App\Filament\Widgets;

use App\Models\DataAlternatif;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters; // Import the trait for dashboard filters

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Tingkat Kenaikan Komoditas';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    // Use the InteractsWithPageFilters trait to access dashboard filters
    use InteractsWithPageFilters; 

    protected function getType(): string
    {
        return 'line'; // A line chart is suitable for showing trends or comparisons
    }

    protected function getData(): array
    {
        // Retrieve filter values from the dashboard
        // The keys 'nama_komoditas', 'startDate', 'endDate' must match the names of your filter components
        $selectedKomoditas = $this->filters['nama_komoditas'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // Initialize the base query for DataAlternatif model
        $baseQuery = DataAlternatif::query();

        // Apply date range filters if start and/or end dates are provided to the base query
        if ($startDate) {
            $baseQuery->whereDate('tanggal', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('tanggal', '<=', $endDate);
        }

        $datasets = [];
        $labels = [];
        
        // Define a set of distinct colors for the chart lines
        $colors = [
            '#4CAF50', // Green
            '#2196F3', // Blue
            '#FFC107', // Amber
            '#F44336', // Red
            '#9C27B0', // Purple
            '#FF9800', // Orange
            '#00BCD4', // Cyan
            '#607D8B', // Blue Grey
        ];
        $colorIndex = 0;

        if ($selectedKomoditas) {
            // If a specific commodity is selected, filter by it
            $data = $baseQuery->clone() // Clone the base query to add commodity filter
                              ->where('nama_komoditas', $selectedKomoditas)
                              ->selectRaw('lokasi_pasar, AVG(harga) as average_harga')
                              ->groupBy('lokasi_pasar')
                              ->get();

            $labels = $data->pluck('lokasi_pasar')->toArray();
            $prices = $data->pluck('average_harga')->toArray();

            $datasets[] = [
                'label' => "Harga Rata-rata {$selectedKomoditas}",
                'data' => $prices,
                'fill' => 'start',
                'borderColor' => $colors[$colorIndex % count($colors)], // Assign a color
                'backgroundColor' => 'rgba(76, 175, 80, 0.2)', 
                'tension' => 0.4,
            ];
        } else {
            // If no commodity is selected, display all commodities with different lines
            $allData = $baseQuery->get();
            // Debugging: Dump all fetched data to inspect
            // dd($allData->toArray()); 

            $uniqueKomoditas = $allData->pluck('nama_komoditas')->unique()->toArray();
            // Debugging: Dump unique commodities detected
            // dd($uniqueKomoditas); 

            $allLokasiPasar = $allData->pluck('lokasi_pasar')->unique()->toArray();

            // Sort labels for consistent display
            sort($allLokasiPasar);
            $labels = $allLokasiPasar;

            foreach ($uniqueKomoditas as $komoditas) {
                // Filter data for the current commodity
                $commodityData = $allData->where('nama_komoditas', $komoditas);

                // Group by lokasi_pasar and calculate average price for this commodity
                $groupedData = $commodityData->groupBy('lokasi_pasar')->map(function ($items) {
                    return $items->avg('harga');
                });

                // Create a prices array ensuring all labels have a corresponding price (or null if missing)
                $pricesForKomoditas = [];
                foreach ($labels as $lokasiPasar) {
                    $pricesForKomoditas[] = $groupedData->get($lokasiPasar);
                }

                $datasets[] = [
                    'label' => $komoditas,
                    'data' => $pricesForKomoditas,
                    'fill' => false, // Don't fill for multiple lines to avoid overlap
                    'borderColor' => $colors[$colorIndex % count($colors)], // Assign a unique color
                    'backgroundColor' => $colors[$colorIndex % count($colors)] . '20', // Lighter background color
                    'tension' => 0.4,
                    'pointRadius' => 3, // Show points for clarity
                    'pointBackgroundColor' => $colors[$colorIndex % count($colors)],
                ];
                $colorIndex++;
            }
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        // Define additional options for the chart for better presentation and user experience
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true, // Start the Y-axis from zero
                    'title' => [
                        'display' => true,
                        'text' => 'Harga (IDR)', // Label for the Y-axis
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Lokasi Pasar', // Label for the X-axis
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'mode' => 'index', // Show tooltip for all datasets at the hovered point
                    'intersect' => false, // Tooltip shows even if not directly on a point
                ],
                'legend' => [
                    'display' => true, // Display the legend for the dataset
                ],
            ],
            'responsive' => true, // Make the chart responsive to container size changes
            'maintainAspectRatio' => false, // Do not force a specific aspect ratio
        ];
    }
}
