<?php

namespace App\Filament\Resources\SubKriteriaResource\Pages;

use App\Filament\Resources\SubKriteriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ListSubKriterias extends ManageRecords
{
    protected static string $resource = SubKriteriaResource::class;
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
