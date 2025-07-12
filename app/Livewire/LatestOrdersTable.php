<?php

namespace App\Livewire;

use Filament\Widgets\TableWidget;
use Filament\Tables\Table;
use Filament\Tables;
use App\Filament\Resources\DataAlternatifResource;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use App\Models\DataAlternatif;
use App\Models\InputData;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class LatestOrdersTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Harga Komoditas';

    public $search = '';
    public $perPage = 10;
    protected $queryString = ['search', 'perPage'];

    public array $filters = [
        'nama_komoditas' => null,
        'startDate' => null,
        'endDate' => null,
    ];

    #[On('filters-updated')]
    public function updateFilters(array $filters): void
    {
        $this->filters = $filters;
        $this->resetTable();
    }

    public function getKomoditasOptionsProperty(): array
    {
        return DataAlternatif::query()
            ->distinct('nama_komoditas')
            ->orderBy('nama_komoditas')
            ->pluck('nama_komoditas')
            ->toArray();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        $query = $this->getFilteredQuery();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama_komoditas', 'like', '%' . $this->search . '%')
                  ->orWhere('lokasi_pasar', 'like', '%' . $this->search . '%')
                  ->orWhere('harga', 'like', '%' . $this->search . '%');
            });
        }

        return $table
            ->query($query)
            ->defaultPaginationPageOption($this->perPage)
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('No')
                    ->rowIndex()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('nama_komoditas')
                    ->label('Komoditas')
                    ->searchable()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('inputData.satuan')
                    ->label('Satuan')
                    ->searchable()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('harga_sebelumnya')
                    ->label('Harga Sebelumnya')
                    ->prefix('Rp ')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('harga')
                    ->label('Harga Terkini')
                    ->prefix('Rp ')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('lokasi_pasar')
                    ->label('Lokasi Pasar')
                    ->searchable()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('perbedaan_harga_tertata')
                    ->label('Laju Inflasi')
                    ->suffix('%')
                    ->alignCenter()
                    ->color(fn ($state) => $this->getInflationColor($state))
                    ->formatStateUsing(fn ($state) => $this->getInflationArrow($state) . $state)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d M Y'))
                    ->sortable(false),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->headerActions([]);
    }

    public function render(): View
    {
        return view('livewire.latest-orders-table', [
            'filteredData' => $this->getTableData(),
        ]);
    }

    protected function getFilteredQuery(): Builder
    {
        $query = DataAlternatif::query();
        
        if ($this->filters['nama_komoditas']) {
            $query->where('nama_komoditas', $this->filters['nama_komoditas']);
        }

        if ($this->filters['startDate']) {
            $query->whereDate('tanggal', '>=', $this->filters['startDate']);
        }

        if ($this->filters['endDate']) {
            $query->whereDate('tanggal', '<=', $this->filters['endDate']);
        }

        return $query;
    }

    protected function getTableData()
    {
        try {
            $query = $this->getFilteredQuery();
            
            if ($this->search) {
                $query->where(function ($q) {
                    $q->where('nama_komoditas', 'like', '%' . $this->search . '%')
                      ->orWhere('lokasi_pasar', 'like', '%' . $this->search . '%')
                      ->orWhere('harga', 'like', '%' . $this->search . '%');
                });
            }
            
            return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
        } catch (\Exception $e) {
            \Log::error('Error getting table data: ' . $e->getMessage());
            return collect();
        }
    }

    protected function getInflationColor($state): string
    {
        if (is_null($state) || $state === '') {
            return 'gray';
        }
        
        $value = (float) str_replace(['↑ ', '↓ ', '%'], '', $state);
        return $value >= 0 ? 'danger' : 'success';
    }

    protected function getInflationArrow($state): string
    {
        if (is_null($state) || $state === '') {
            return '';
        }
        
        $value = (float) str_replace(['↑ ', '↓ ', '%'], '', $state);
        return $value >= 0 ? '↑ ' : '↓ ';
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
}