<?php

namespace App\Filament\Resources\DataAlternatifResource\Pages;

use App\Filament\Resources\DataAlternatifResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataAlternatif extends ListRecords
{
    protected static string $resource = DataAlternatifResource::class;
    
   
    public function getHeaderActions(): array
    {
        $decodeQueryString = urldecode(request()->getQueryString());
    
        return [
            Actions\Action::make('export-dat alternatif')
            ->label('Ekspor')
            ->icon('heroicon-o-arrow-down-tray') // Export/download icon
            ->color('success')
            ->url(url('/export1?' . $decodeQueryString))  // You may want to handle query parameters
            ->openUrlInNewTab(),
        ];
    }
    
    }
    

