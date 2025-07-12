<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DataAlternatifResource;
use App\Models\DataAlternatif;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
// CORRECTED: Use InteractsWithPageFilters trait for widgets to get dashboard filters
use Filament\Widgets\Concerns\InteractsWithPageFilters; 

class LatestOrders extends BaseWidget
{
    // Make sure this widget spans the full width of the dashboard
    protected int | string | array $columnSpan = 'full';

    // Set a sort order for the widget on the dashboard
    protected static ?int $sort = 2;

    // CORRECTED: Use the InteractsWithPageFilters trait to access dashboard filters
    use InteractsWithPageFilters; 

    public function table(Table $table): Table
    {
        // Get the selected 'nama_komoditas' from the dashboard filters.
        // The key 'nama_komoditas' must match the name of your Select component in Dashboard.php.
        $selectedKomoditas = $this->filters['nama_komoditas'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // Start with the base Eloquent query from your DataAlternatifResource
        $query = DataAlternatifResource::getEloquentQuery();

        // Apply the commodity filter if a value is selected
        if ($selectedKomoditas) {
            $query->where('nama_komoditas', $selectedKomoditas);
        }

        // Apply date filters if they are set
        if ($startDate) {
            $query->whereDate('tanggal', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('tanggal', '<=', $endDate);
        }

        return $table
            ->query($query) // Use the filtered query
            ->defaultPaginationPageOption(5) // Show 5 records per page by default
            ->defaultSort('created_at', 'desc') // Sort by creation date, newest first
            ->columns([
                Tables\Columns\TextColumn::make('lokasi_pasar')
                    ->label('Lokasi Pasar')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_komoditas')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('harga')
                    ->label('Harga Sekarang')
                    ->money('IDR') // Format as Indonesian Rupiah
                    ->sortable()
                    ->color('primary'), // Use the primary theme color for the price
                Tables\Columns\TextColumn::make('perbedaan_harga_tertata')
                    ->label('Laju Inflasi')
                    ->suffix('%') // Add a percentage sign
                    ->alignCenter()
                    // Dynamically color the text based on the inflation rate: green for positive/zero, red for negative
                    ->color(fn ($state) => (float) str_replace(['↑ ', '↓ ', '%'], '', $state) >= 0 ? 'success' : 'danger')
                    // This prefix logic is usually handled by the data source if you want actual arrows.
                    // If 'perbedaan_harga_tertata' already includes '↑' or '↓', this line might be redundant.
                    ->prefix(fn ($state) => (float) str_replace(['↑ ', '↓ ', '%'], '', $state) >= 0 ? '' : ''),
            ]);
    }
}