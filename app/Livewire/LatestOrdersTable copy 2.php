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

    /**
     * Override getEloquentQuery untuk menampilkan hanya data terbaru
     * berdasarkan kombinasi nama_komoditas dan lokasi_pasar
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('penilaian.*')
            ->join('data_alternatifs', 'penilaian.alternatif_id', '=', 'data_alternatifs.id_alternatif')
            ->whereIn('penilaian.id', function ($query) {
                $query->select(DB::raw('MAX(p.id)'))
                    ->from('penilaian as p')
                    ->join('data_alternatifs as da', 'p.alternatif_id', '=', 'da.id_alternatif')
                    ->groupBy('p.nama_komoditas', 'da.lokasi_pasar');
            })
            ->with('dataAlternatif');
    }

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
            ->columns([
                Tables\Columns\TextColumn::make('id_penilaian')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('nama_komoditas')
                    ->label('Komoditas')
                    ->sortable()
                    ->searchable(),
                    
                TextColumn::make('dataAlternatif.lokasi_pasar')
                    ->label('Lokasi Pasar')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('normalizedC1')
                    ->label('C1')
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC1'])
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('normalizedC2')
                    ->label('C2')
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC2'])
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('normalizedC3')
                    ->label('C3')
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC3'])
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('normalizedC4')
                    ->label('C4')
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC4'])
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('normalizedC5')
                    ->label('C5')
                    ->state(fn ($record) => $record->normalizePenilaianValues()['normalizedC5'])
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('maut_score')
                    ->label('Skor MAUT')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderByMautScore($direction);
                    }),
                
                TextColumn::make('ranking')
                    ->label('Peringkat')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 3 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderByMautScore($direction === 'asc' ? 'desc' : 'asc');
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_mulai')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('tanggal_akhir')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query->when(
                            $data['tanggal_mulai'],
                            fn ($query) => $query->whereDate('penilaian.tanggal', '>=', $data['tanggal_mulai'])
                        )->when(
                            $data['tanggal_akhir'],
                            fn ($query) => $query->whereDate('penilaian.tanggal', '<=', $data['tanggal_akhir'])
                        );
                    }),
                    
                Tables\Filters\SelectFilter::make('nama_komoditas')
                    ->label('Komoditas')
                    ->options(fn () => Penilaian::distinct('nama_komoditas')
                        ->pluck('nama_komoditas', 'nama_komoditas')),

                Tables\Filters\SelectFilter::make('lokasi_pasar')
                    ->label('Lokasi Pasar')
                    ->options(fn () => \App\Models\DataAlternatif::distinct('lokasi_pasar')
                        ->pluck('lokasi_pasar', 'lokasi_pasar')),
            ])
            ->actions([
                // Tidak ada aksi per baris untuk resource ini
            ])
            ->headerActions([
                Action::make('exportPdf')
                    ->label('Ekspor PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (): string => route('filament.admin.resources.hasil-akhirs.export-pdf', request()->query()))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->action(function ($action, $records) {
                        foreach ($records as $record) {
                            $dataAlternatif = $record->dataAlternatif;
                            if ($dataAlternatif) {
                                $inputData = $dataAlternatif->inputData;
                                if ($inputData) $inputData->delete();
                                else $dataAlternatif->delete();
                            } else {
                                $record->delete();
                            }
                        }
                        \Filament\Notifications\Notification::make()->title('Berhasil dihapus')->body('Semua record yang dipilih dan data turunan telah dihapus.')->success()->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->defaultSort('ranking', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHasilAkhirs::route('/'),
        ];
    }

    /**
     * Metode statis untuk menangani ekspor PDF.
     */
    public static function exportPdf()
    {
        // Ambil query dasar dari resource dengan filter duplikasi
        $query = static::getEloquentQuery();
        
        // Ambil filter dari request
        $filters = request()->query();

        if (isset($filters['filters'])) {
            $parsedFilters = json_decode($filters['filters'], true);
            
            // Terapkan filter tanggal jika ada
            if (isset($parsedFilters['tanggal']['tanggal_mulai'])) {
                $query->whereDate('penilaian.tanggal', '>=', $parsedFilters['tanggal']['tanggal_mulai']);
            }
            if (isset($parsedFilters['tanggal']['tanggal_akhir'])) {
                $query->whereDate('penilaian.tanggal', '<=', $parsedFilters['tanggal']['tanggal_akhir']);
            }
            
            // Terapkan filter komoditas jika ada
            if (isset($parsedFilters['nama_komoditas'])) {
                $query->where('penilaian.nama_komoditas', $parsedFilters['nama_komoditas']);
            }
            
            // Terapkan filter lokasi pasar jika ada
            if (isset($parsedFilters['lokasi_pasar'])) {
                $query->where('data_alternatifs.lokasi_pasar', $parsedFilters['lokasi_pasar']);
            }
        }
        
        // Ambil data setelah filter diterapkan dan urutkan berdasarkan skor MAUT
        $records = $query->orderByMautScore('desc')->get();

        // Render view Blade ke HTML
        $html = Blade::render(view('filament.exports.hasil-akhir-pdf', compact('records'))->render());

        // Konfigurasi Dompdf
        $options = new Options();
        $options->set('defaultFont', 'sans-serif');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        // Inisialisasi Dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);

        // Atur ukuran kertas dan orientasi
        $dompdf->setPaper('A4', 'portrait');

        // Render HTML ke PDF
        $dompdf->render();

        // Stream PDF ke browser sebagai unduhan
        return Response::stream(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan_hasil_normalisasi_dinas_perdagangan_palembang_' . date('Ymd_His') . '.pdf"',
        ]);
    }
}