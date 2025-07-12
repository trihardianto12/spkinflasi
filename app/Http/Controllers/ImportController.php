<?php

namespace App\Http\Controllers;

use App\Imports\ImportInputData;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Added Log facade import

class ImportController extends Controller
{
    public function import(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            // Store the file securely
            $file = $request->file('file');
            $fileName = 'input_data_import_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('imports/input_data', $fileName);

            // Execute the import
            $import = new ImportInputData();
            Excel::import($import, $file);

            // Check if there were any failures
            if (method_exists($import, 'failures') && $import->failures()->isNotEmpty()) {
                $errorCount = count($import->failures());
                
                Notification::make()
                    ->title('Import Completed with Errors')
                    ->warning()
                    ->body("Successfully imported data with {$errorCount} errors")
                    ->send();
                    
                Log::warning("Input data import completed with {$errorCount} errors", [
                    'file' => $fileName
                ]);
            } else {
                Notification::make()
                    ->title('Import Successful')
                    ->success()
                    ->body('All data imported successfully')
                    ->send();
                    
                Log::info("Input data imported successfully", [
                    'file' => $fileName,
                    'rows' => $import->getRowCount()
                ]);
            }

            // Clean up - delete the file after import
            Storage::delete($path);

            return redirect()->route('filament.admin.resources.input-data.index');

        } catch (\Exception $e) {
            Log::error('Import Error: ' . $e->getMessage(), [
                'file' => $request->file('file')->getClientOriginalName(),
                'exception' => $e
            ]);
            
            Notification::make()
                ->title('Import Failed')
                ->danger()
                ->body('Error importing file: ' . $e->getMessage())
                ->send();

            return back()->withInput();
        }
    }
}