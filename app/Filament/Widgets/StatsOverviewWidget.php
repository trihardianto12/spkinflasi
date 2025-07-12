<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use App\Models\InputData;
use Illuminate\Support\Facades\Log; // Tambahkan ini

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $filters = $this->filters;

        $startDate = isset($filters['startDate'])
            ? Carbon::parse($filters['startDate'])
            : now()->subMonth();

        $endDate = isset($filters['endDate'])
            ? Carbon::parse($filters['endDate'])
            : now();

        $namaKomoditas = $filters['nama_komoditas'] ?? null;

        // --- Mulai Debugging ---
        Log::info('Dashboard Filters:', $filters);
        Log::info('Parsed Start Date:', ['date' => $startDate->toDateString()]);
        Log::info('Parsed End Date:', ['date' => $endDate->toDateString()]);
        Log::info('Selected Komoditas:', ['komoditas' => $namaKomoditas]);
        // --- Akhir Debugging ---

        $query = InputData::query()
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($namaKomoditas) {
            $query->where('nama_komoditas', $namaKomoditas);
        }

        // --- Mulai Debugging ---
        Log::info('Query for Approved Count:', [
            'sql' => (clone $query)->where('status', 'Approved')->toSql(),
            'bindings' => (clone $query)->where('status', 'Approved')->getBindings()
        ]);
        // --- Akhir Debugging ---

        // Hitung jumlah masing-masing status
        $pendingCount = (clone $query)->where('status', 'Pending')->count();
        $approvedCount = (clone $query)->where('status', 'Approved')->count();
        $rejectedCount = (clone $query)->where('status', 'Rejected')->count();

        // --- Mulai Debugging ---
        Log::info('Counts:', [
            'pending' => $pendingCount,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount
        ]);
        // --- Akhir Debugging ---

        $formatNumber = function (int $number): string {
            if ($number < 1000) {
                return (string) Number::format($number, 0);
            }
            if ($number < 1000000) {
                return Number::format($number / 1000, 2) . 'k';
            }
            return Number::format($number / 1000000, 2) . 'm';
        };

        return [
            Stat::make('Pending', $formatNumber($pendingCount))
                ->description('Menunggu persetujuan')
                ->descriptionIcon('heroicon-m-clock')
                ->chart([$pendingCount, 10, 8, 6, 7, 9, 5])
                ->color('warning'),

            Stat::make('Approved', $formatNumber($approvedCount))
                ->description('Disetujui')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart([$approvedCount, 12, 14, 15, 13, 17, 11])
                ->color('success'),

            Stat::make('Rejected', $formatNumber($rejectedCount))
                ->description('Ditolak')
                ->descriptionIcon('heroicon-m-x-circle')
                ->chart([$rejectedCount, 2, 3, 1, 1, 0, 4])
                ->color('danger'),
        ];
    }
}