<?php

namespace App\Filament\Resources\InputDataResource\Pages;

use App\Filament\Resources\InputDataResource;
use Filament\Actions;
 // If you still need to import categories
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use App\Imports\InputDataImport; // Import the correct importer for InputData
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ListInputData extends ListRecords
{
    protected static string $resource = InputDataResource::class;

    /**
     * Get the header actions for the page.
     * These actions are displayed at the top of the list records page.
     */
    protected function getHeaderActions(): array
    {
        $decodeQueryString = urldecode(request()->getQueryString());

        return [
            // Export action for input data
            Actions\Action::make('export')
                ->label('Ekspor')
                ->icon('heroicon-o-arrow-down-tray') // Export/download icon
                ->color('success')
                ->url(url('/export?' . $decodeQueryString))  // You may want to handle query parameters
                ->openUrlInNewTab(),
            
                

            // Create action for adding new input data
            Actions\CreateAction::make()
                ->label('Tambah Data')
                ->icon('heroicon-o-plus-circle') // Add/plus icon
                ->color('primary'),

            // Import action for categories (you can change to InputDataImport if necessary)
            // Actions\ImportAction::make()
            //     ->importer(InputDataImporter::class),  // Make sure this is the correct importer
        ];
    }

    /**
     * Define the page view (optional: only needed if you want custom page content).
     */
   
}
