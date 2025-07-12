<?php

namespace App\Http\Controllers;

use App\Exports\DataAlternatifExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportAlternatifController extends Controller
{
    public function export1(Request $request)
    {
        // Validasi input
        $request->validate([
            'status' => 'nullable|string',
            'dari_tanggal' => 'nullable|date',
            'sampai_tanggal' => 'nullable|date'
        ]);
    
        // Menyiapkan filter yang diterima dari request
        $filters = [
            'status' => $request->input('status'),
            'dari_tanggal' => $request->input('dari_tanggal'),
            'sampai_tanggal' => $request->input('sampai_tanggal'),
        ];
    
        // Membuat nama file ekspor
        $filename = 'data_alternatif_' . now()->format('Ymd_His') . '.xlsx';
    
        // Menggunakan ExportDataAlternatif untuk ekspor dan mengirimkan filter yang diterima
        return Excel::download(new DataAlternatifExport($filters), $filename);
    }
}
