<?php

namespace App\Filament\Resources\KriteriaResource\Pages;

use App\Filament\Resources\KriteriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKriterias extends ListRecords
{
    protected static string $resource = KriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
             ->label('Tambah Data')
             ->icon('heroicon-o-plus-circle') // Ikon tambah/plus
            ->color('primary'),
           
        ];
    }
}
