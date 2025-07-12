<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HasilAkhirResource\Pages;
use App\Models\HasilAkhir;
use App\Models\Penilaian;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

class HasilAkhirResource extends Resource
{
    protected static ?string $model = HasilAkhir::class;
    protected static ?string $navigationLabel = 'Hasil Akhir';
    protected static ?string $label = 'Hasil Akhir';
    protected static ?string $pluralLabel = 'Hasil Akhir';
    protected static ?string $navigationGroup = 'Perhitungan';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Data')
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai'),
                        Forms\Components\DatePicker::make('tanggal_akhir')
                            ->label('Tanggal Akhir'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Cek apakah ada filter tanggal aktif
                $hasDateFilter = request()->has('tableFilters.tanggal.tanggal_mulai') ||
                               request()->has('tableFilters.tanggal.tanggal_akhir') ||
                               request()->has('filters.tanggal.tanggal_mulai') ||
                               request()->has('filters.tanggal.tanggal_akhir'); //
                
                if (!$hasDateFilter) {
                    // Jika tidak ada filter tanggal, gunakan data terbaru per kombinasi komoditas-lokasi
                    $latestRecords = DB::table('penilaian as p1')
                        ->join('data_alternatif as da1', 'p1.alternatif_id', '=', 'da1.id') //
                        ->select('p1.id') //
                        ->whereRaw('p1.tanggal = (
                            SELECT MAX(p2.tanggal) 
                            FROM penilaian p2 
                            JOIN data_alternatif da2 ON p2.alternatif_id = da2.id
                            WHERE p2.nama_komoditas = p1.nama_komoditas 
                            AND da2.lokasi_pasar = da1.lokasi_pasar
                        )') //
                        ->pluck('id'); //
                    
                    $query->whereIn('id', $latestRecords); //
                } else {
                    // Jika ada filter tanggal, ambil semua data dalam rentang tanggal
                    // Filter tanggal akan ditangani oleh filter table secara otomatis
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id_penilaian')
                    ->label('No') //
                    ->rowIndex(),
                TextColumn::make('nama_komoditas')
                    ->label('Komoditas') //
                    ->sortable() //
                    ->searchable(), //
                    
                TextColumn::make('dataAlternatif.lokasi_pasar')
                    ->label('Lokasi Pasar') //
                    ->sortable() //
                    ->searchable(), //

                TextColumn::make('tanggal')
                    ->label('Tanggal') //
                    ->date('d/m/Y') //
                    ->sortable(), //

                TextColumn::make('normalizedC1')
                    ->label('C1') //
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC1']) //
                    ->numeric(decimalPlaces: 2), //

                TextColumn::make('normalizedC2')
                    ->label('C2') //
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC2']) //
                    ->numeric(decimalPlaces: 2), //

                TextColumn::make('normalizedC3')
                    ->label('C3') //
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC3']) //
                    ->numeric(decimalPlaces: 2), //

                TextColumn::make('normalizedC4')
                    ->label('C4') //
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC4']) //
                    ->numeric(decimalPlaces: 2), //

                TextColumn::make('normalizedC5')
                    ->label('C5') //
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC5']) //
                    ->numeric(decimalPlaces: 2), //

                TextColumn::make('maut_score')
                    ->label('Skor MAUT') //
                    ->numeric(decimalPlaces: 2) //
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderByMautScore($direction); //
                    }),
                
                TextColumn::make('ranking')
                    ->label('Peringkat') //
                    ->badge() //
                    ->color(fn ($state) => match (true) {
                        $state <= 3 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }) //
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderByMautScore($direction === 'asc' ? 'desc' : 'asc'); //
                    })
                    ->state(function ($record) {
                        // Paksa recalculate ranking untuk setiap record
                        return $record->ranking; //
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_mulai')
                            ->label('Dari Tanggal'), //
                        Forms\Components\DatePicker::make('tanggal_akhir')
                            ->label('Sampai Tanggal'), //
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['tanggal_mulai'],
                            fn ($query) => $query->whereDate('tanggal', '>=', $data['tanggal_mulai']) //
                        )->when(
                            $data['tanggal_akhir'],
                            fn ($query) => $query->whereDate('tanggal', '<=', $data['tanggal_akhir']) //
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['tanggal_mulai'] && ! $data['tanggal_akhir']) {
                            return null;
                        }
                        
                        $indication = 'Tanggal: '; //
                        if ($data['tanggal_mulai']) {
                            $indication .= 'dari ' . \Carbon\Carbon::parse($data['tanggal_mulai'])->format('d/m/Y'); //
                        }
                        if ($data['tanggal_akhir']) {
                            $indication .= ' sampai ' . \Carbon\Carbon::parse($data['tanggal_akhir'])->format('d/m/Y'); //
                        }
                        
                        return $indication;
                    }),
                    
                Tables\Filters\SelectFilter::make('nama_komoditas')
                    ->label('Komoditas') //
                    ->options(fn () => Penilaian::distinct('nama_komoditas')
                        ->pluck('nama_komoditas', 'nama_komoditas')), //
                        
                Tables\Filters\SelectFilter::make('lokasi_pasar')
                    ->label('Lokasi Pasar') //
                    ->options(fn () => \App\Models\DataAlternatif::distinct('lokasi_pasar')
                        ->pluck('lokasi_pasar', 'lokasi_pasar')) //
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value'])) {
                            $query->whereHas('dataAlternatif', function ($q) use ($data) {
                                $q->where('lokasi_pasar', $data['value']); //
                            });
                        }
                    }),
            ])
            ->actions([
                // Tidak ada aksi per baris untuk resource ini
            ])
            ->headerActions([
                Action::make('exportPdf')
                    ->label('Ekspor PDF') //
                    ->icon('heroicon-o-document-arrow-down') //
                    ->color('success') //
                    ->url(fn (): string => route('filament.admin.resources.hasil-akhirs.export-pdf', request()->query())) //
                    ->openUrlInNewTab(), //
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->action(function ($action, $records) {
                        foreach ($records as $record) {
                            $dataAlternatif = $record->dataAlternatif; //
                            if ($dataAlternatif) {
                                $inputData = $dataAlternatif->inputData; //
                                if ($inputData) $inputData->delete(); //
                                else $dataAlternatif->delete(); //
                            } else {
                                $record->delete(); //
                            }
                        }
                        \Filament\Notifications\Notification::make()->title('Berhasil dihapus')->body('Semua record yang dipilih dan data turunan telah dihapus.')->success()->send(); //
                    })
                    ->requiresConfirmation(), //
            ])
            ->defaultSort('ranking', 'asc')
            ->poll('5s')
            ->loadingIndicator()
            ->reorderable(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHasilAkhirs::route('/'),
        ];
    }

    public static function exportPdf()
    {
        $query = static::getEloquentQuery(); //
        $filters = request()->query(); //
        
        // Cek apakah ada filter tanggal
        $hasDateFilter = false;
        if (isset($filters['tableFilters'])) {
            $hasDateFilter = isset($filters['tableFilters']['tanggal']['tanggal_mulai']) || 
                           isset($filters['tableFilters']['tanggal']['tanggal_akhir']); //
        } elseif (isset($filters['filters'])) {
            $parsedFilters = json_decode($filters['filters'], true); //
            $hasDateFilter = isset($parsedFilters['tanggal']['tanggal_mulai']) || 
                           isset($parsedFilters['tanggal']['tanggal_akhir']); //
        }
        
        if (!$hasDateFilter) {
            // Jika tidak ada filter tanggal, gunakan data terbaru per kombinasi komoditas-lokasi
            $latestRecords = DB::table('penilaian as p1')
                ->join('data_alternatif as da1', 'p1.alternatif_id', '=', 'da1.id') //
                ->select('p1.id') //
                ->whereRaw('p1.tanggal = (
                    SELECT MAX(p2.tanggal) 
                    FROM penilaian p2 
                    JOIN data_alternatif da2 ON p2.alternatif_id = da2.id
                    WHERE p2.nama_komoditas = p1.nama_komoditas 
                    AND da2.lokasi_pasar = da1.lokasi_pasar
                )') //
                ->pluck('id'); //
            
            $query->whereIn('id', $latestRecords); //
        }
    
        // Terapkan filter dari tableFilters
        if (isset($filters['tableFilters'])) {
            if (isset($filters['tableFilters']['tanggal'])) {
                if (isset($filters['tableFilters']['tanggal']['tanggal_mulai'])) {
                    $query->whereDate('tanggal', '>=', $filters['tableFilters']['tanggal']['tanggal_mulai']); //
                }
                if (isset($filters['tableFilters']['tanggal']['tanggal_akhir'])) {
                    $query->whereDate('tanggal', '<=', $filters['tableFilters']['tanggal']['tanggal_akhir']); //
                }
            }
            
            if (isset($filters['tableFilters']['nama_komoditas'])) {
                $query->where('nama_komoditas', $filters['tableFilters']['nama_komoditas']); //
            }
            
            if (isset($filters['tableFilters']['lokasi_pasar'])) {
                $query->whereHas('dataAlternatif', function ($q) use ($filters) {
                    $q->where('lokasi_pasar', $filters['tableFilters']['lokasi_pasar']); //
                });
            }
        }
        // Terapkan filter dari URL
        elseif (isset($filters['filters'])) {
            $parsedFilters = json_decode($filters['filters'], true); //
            
            if (isset($parsedFilters['tanggal'])) {
                if (isset($parsedFilters['tanggal']['tanggal_mulai'])) {
                    $query->whereDate('tanggal', '>=', $parsedFilters['tanggal']['tanggal_mulai']); //
                }
                if (isset($parsedFilters['tanggal']['tanggal_akhir'])) {
                    $query->whereDate('tanggal', '<=', $parsedFilters['tanggal']['tanggal_akhir']); //
                }
            }
            
            if (isset($parsedFilters['nama_komoditas'])) {
                $query->where('nama_komoditas', $parsedFilters['nama_komoditas']); //
            }
            
            if (isset($parsedFilters['lokasi_pasar'])) {
                $query->whereHas('dataAlternatif', function ($q) use ($parsedFilters) {
                    $q->where('lokasi_pasar', $parsedFilters['lokasi_pasar']); //
                });
            }
        }
        
        // Urutkan berdasarkan skor MAUT
        $records = $query->orderByMautScore('desc')->get(); //
    
        // Generate PDF
        $html = Blade::render(view('filament.exports.hasil-akhir-pdf', compact('records'))->render()); //
    
        $options = new Options();
        $options->set('defaultFont', 'sans-serif'); //
        $options->set('isHtml5ParserEnabled', true); //
        $options->set('isRemoteEnabled', true); //
    
        $dompdf = new Dompdf($options); //
        $dompdf->loadHtml($html); //
        $dompdf->setPaper('A4', 'portrait'); //
        $dompdf->render(); //
    
        return Response::stream(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan_hasil_normalisasi_dinas_perdagangan_palembang_' . date('Ymd_His') . '.pdf"',
        ]); //
    }
}