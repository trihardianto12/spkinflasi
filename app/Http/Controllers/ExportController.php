<?php

namespace App\Http\Controllers;

use App\Exports\ExportInputData;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function export(Request $request)
    {
        // Validasi input
        $request->validate([
            'status' => 'nullable|string',
            'dari_tanggal' => 'nullable|date',
            'sampai_tanggal' => 'nullable|date'
        ]);

        // Menyiapkan filter yang diterima dari request
        $filters = [
            'status' => $request->input('status'),  // Status filter
            'dari_tanggal' => $request->input('dari_tanggal'),  // Tanggal mulai
            'sampai_tanggal' => $request->input('sampai_tanggal'),  // Tanggal akhir
        ];

        // Membuat nama file ekspor
        $filename = 'data_harga_komoditas_' . now()->format('Ymd_His') . '.xlsx';

        // Menggunakan ExportInputData untuk ekspor dan mengirimkan filter yang diterima
        return Excel::download(new ExportInputData($filters), $filename);
    }
}
    
