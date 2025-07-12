<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use App\Models\DataAlternatif;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Resources\DataAlternatifResource;


class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('nama_komoditas')
                            ->label('Nama Komoditas')
                            ->options(DataAlternatif::query()->pluck('nama_komoditas', 'nama_komoditas'))
                            ->placeholder('Pilih Komoditas')
                            ->live()
                            ->columnSpan(1),
                        DatePicker::make('startDate')
                            // Mengubah default startDate ke awal bulan April 2025 (atau rentang yang relevan dengan data Anda)
                            // Contoh: now()->subMonths(2)->startOfMonth()->format('Y-m-d')
                            // Atau untuk spesifik April 2025: '2025-04-01'
                            ->default(now()->subMonths(2)->startOfMonth()->format('Y-m-d')) // Ini akan mencakup April
                            ->live(),
                        DatePicker::make('endDate')
                            ->default(now()->format('Y-m-d'))
                            ->live(),
                    ])
                    ->columns(3),
            ]);
    }

    
}