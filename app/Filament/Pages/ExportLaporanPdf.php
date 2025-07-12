<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use App\Models\Penilaian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;

class ExportLaporanPdf extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static string $view = 'filament.pages.export-laporan-pdf';
    protected static ?string $title = 'Ekspor Laporan PDF';
    protected static ?string $navigationLabel = 'Ekspor Laporan PDF';

      protected static bool $shouldRegisterNavigation = false;
   
    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('tanggal_mulai')
                    ->label('Dari Tanggal')
                    ->placeholder('Pilih tanggal mulai'),
                DatePicker::make('tanggal_akhir')
                    ->label('Sampai Tanggal')
                    ->placeholder('Pilih tanggal akhir'),
                Select::make('nama_komoditas')
                    ->label('Komoditas')
                    ->placeholder('Pilih komoditas (opsional)')
                    ->options(fn () => Penilaian::distinct('nama_komoditas')->pluck('nama_komoditas', 'nama_komoditas'))
                    ->searchable(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('export')
                ->label('Unduh Laporan PDF')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportPdf'),
        ];
    }

    public function exportPdf()
    {
        $data = $this->form->getState();

        $query = Penilaian::query();

        if (!empty($data['tanggal_mulai'])) {
            $query->whereDate('tanggal', '>=', $data['tanggal_mulai']);
        }
        if (!empty($data['tanggal_akhir'])) {
            $query->whereDate('tanggal', '<=', $data['tanggal_akhir']);
        }
        if (!empty($data['nama_komoditas'])) {
            $query->where('nama_komoditas', $data['nama_komoditas']);
        }

        $records = $query->orderByMautScore('desc')->get();

        $pdf = Pdf::loadView('filament.exports.hasil-akhir-pdf', [
            'records' => $records
        ]);

        return Response::streamDownload(
            fn () => print($pdf->output()),
            'laporan_hasil_normalisasi_dinas_perdagangan_palembang_' . date('Ymd_His') . '.pdf'
        );
    }
}