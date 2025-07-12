<?php

namespace App\Filament\Resources\InputDataResource\Pages;

use App\Filament\Resources\InputDataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInputData extends EditRecord
{
    protected static string $resource = InputDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
