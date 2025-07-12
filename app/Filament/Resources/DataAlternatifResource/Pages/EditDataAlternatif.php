<?php

namespace App\Filament\Resources\DataAlternatifResource\Pages;

use App\Filament\Resources\DataAlternatifResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataAlternatif extends EditRecord
{
    protected static string $resource = DataAlternatifResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
