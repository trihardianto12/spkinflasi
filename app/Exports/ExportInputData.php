<?php

namespace App\Exports;

use App\Models\InputData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ExportInputData implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = InputData::query();
    
        // Filter berdasarkan status
        if ($this->filters['status']) {
            $query->where('status', $this->filters['status']);
        }
    
        // Filter berdasarkan rentang tanggal
        if ($this->filters['dari_tanggal']) {
            // Pastikan untuk menggunakan filter tanggal dari 'dari_tanggal'
            $query->whereDate('tanggal', '>=', $this->filters['dari_tanggal']);
        }
    
        if ($this->filters['sampai_tanggal']) {
            // Pastikan untuk menggunakan filter tanggal dari 'sampai_tanggal'
            $query->whereDate('tanggal', '<=', $this->filters['sampai_tanggal']);
        }
    
        // Ambil data sesuai dengan filter
        return $query->get();
    }
    
    public function headings(): array
    {
        return [
            'No',
            'Nama Komoditas',
            'Jenis Komoditas',
            'Lokasi Pasar',
            'Satuan',
            'Jumlah Permintaan',
            'Asal Pemasok',
            'Harga (Rp)',
            'Tanggal',
            'Tingkat Pasokan',
            'Latitude',
            'Longitude',
        ];
    }

    public function map($row): array
    {
        static $number = 1;

        // Retrieve related descriptions for tingkat_pasokan, jumlah_permintaan, and asal_pemasok
        $tingkatPasokan = \App\Models\SubKriteria::find($row->tingkat_pasokan);
        $jumlahPermintaan = \App\Models\SubKriteria::find($row->jumlah_permintaan);
        $asal = \App\Models\SubKriteria::find($row->asal_pemasok);

        // Map data for export, with descriptions for certain fields
        return [
            $number++,
            $row->nama_komoditas,
            $row->jenis_komoditas,
            $row->lokasi_pasar,
            $row->satuan,
            $jumlahPermintaan ? $jumlahPermintaan->deskripsi : '-', 
            $asal ? $asal->deskripsi : '-', 
            number_format($row->harga, 2),
            $row->tanggal->format('d/m/Y'), // Format date to 'd/m/Y'
            $tingkatPasokan ? $tingkatPasokan->deskripsi : '-', 
            $row->latitude,
            $row->longitude,
        ];
    }

    public function styles($sheet)
    {
        // Apply bold and center alignment for the header row
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:O1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    
        // Set background color for header row
        $sheet->getStyle('A1:O1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:O1')->getFill()->getStartColor()->setRGB('4F81BD'); // Light Blue color
    
        // Set borders around the header row
        $sheet->getStyle('A1:O1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
        // Apply alternating row colors for better readability
        $sheet->getStyle('A2:O1000')->applyFromArray([
            'fill' => [
                'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startcolor' => ['rgb' => 'F2F2F2'] // Light grey color for alternating rows
            ]
        ]);
    
        // Apply borders around all data rows
        $sheet->getStyle('A2:O1000')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
        // Apply font styling for data rows (optional)
        $sheet->getStyle('A2:O1000')->getFont()->setName('Arial')->setSize(10);
    
        // Adjust column width based on content
        $columns = ['A' => 5, 'B' => 20, 'C' => 20, 'D' => 20, 'E' => 10, 'F' => 15, 'G' => 20, 'H' => 15, 'I' => 15, 'J' => 15, 'K' => 20, 'L' => 20];
        foreach ($columns as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    
        // Apply bold styling to some columns, e.g., the 'Harga' column
        $sheet->getStyle('H2:H1000')->getFont()->setBold(true);
    
        // Center align numeric columns (e.g., Harga and Latitude/Longitude)
        $sheet->getStyle('H2:H1000')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K2:L1000')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        // Add a thicker border at the bottom of the header row
        $sheet->getStyle('A1:O1')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
    }
    
    public function columnFormats(): array
    {
        return [
            'F' => '#,##0.00', // Format for Harga (Rp)
            'G' => '#,##0.00', // Format for Harga Sebelumnya (Rp)
            'M' => '#,##0.00', // Format for Selisih Harga
            'O' => '#,##0.00', // Format for Laju Inflasi
            'I' => 'DD/MM/YYYY', // Format for Tanggal
        ];
    }
}
