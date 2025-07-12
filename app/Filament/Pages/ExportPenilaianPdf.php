<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use App\Models\Penilaian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;

class ExportPenilaianPdf extends Page
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $title = 'Ekspor Data Penilaian';
    
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?int $navigationSort = 99;

    // Menambahkan properti ini untuk menyembunyikan halaman dari menu
    protected static ?bool $hiddenFromNavigation = true;

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
                    ->label('Tanggal Mulai')
                    ->placeholder('Pilih tanggal mulai'),
                DatePicker::make('tanggal_akhir')
                    ->label('Tanggal Akhir')
                    ->placeholder('Pilih tanggal akhir'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('export')
                ->label('Unduh PDF')
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
