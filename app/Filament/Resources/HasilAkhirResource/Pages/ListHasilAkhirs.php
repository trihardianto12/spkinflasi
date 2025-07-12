<?php

namespace App\Filament\Resources\HasilAkhirResource\Pages;

use App\Filament\Resources\HasilAkhirResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHasilAkhirs extends ListRecords
{
    protected static string $resource = HasilAkhirResource::class;

    protected function getHeaderActions(): array
    {
        return [
          
        ];
    }
}
