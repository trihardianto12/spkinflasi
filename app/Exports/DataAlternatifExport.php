<?php
namespace App\Exports;

use App\Models\DataAlternatif;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use App\Models\InputData;

class DataAlternatifExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = DataAlternatif::query();
        $query = InputData::query();

        // Apply filters if provided
      

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereBetween('tanggal', [
                $this->filters['start_date'],
                $this->filters['end_date']
            ]);
        }

        return $query->orderBy('tanggal', 'desc')->get();
    }



    public function headings(): array
    {
        return [
            'No',
            'Nama Komoditas',
            'Jenis Komoditas',
            'Lokasi Pasar',
            'Satuan',
            'Harga (Rp)',
            'Harga Sebelumnya (Rp)',
            'Perubahan Harga',
            'Laju Inflasi',
            'Tanggal',
            
        ];
    }

    public function map($row): array
    {
        static $number = 1;

        return [
            $number++,
            $row->nama_komoditas,
            $row->jenis_komoditas,
            $row->lokasi_pasar,
            $row->satuan,
            number_format($row->harga , 2),
            $row->harga_sebelumnya ? number_format($row->harga_sebelumnya, 2) : '-',
            number_format($row->selisih_harga, 2),    
            number_format($row->perbedaan_harga_tertata, 2) . '%', 
            $row->tanggal->format('d/m/Y'),
         
        ];
    }

    public function styles($sheet)
{
    // Apply bold and center alignment for the header row
    $sheet->getStyle('A1:J1')->getFont()->setBold(true);
    $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:J1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    // Set background color for header row
    $sheet->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet->getStyle('A1:J1')->getFill()->getStartColor()->setRGB('4F81BD'); // Light Blue color

    // Set borders around the header row
    $sheet->getStyle('A1:J1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Apply alternating row colors for better readability
    $sheet->getStyle('A2:J1000')->applyFromArray([
        'fill' => [
            'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startcolor' => ['rgb' => 'F2F2F2'] // Light grey color for alternating rows
        ]
    ]);

    // Apply borders around all data rows
    $sheet->getStyle('A2:J1000')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Apply font styling for data rows (optional)
    $sheet->getStyle('A2:J1000')->getFont()->setName('Arial')->setSize(10);

    // Adjust column width based on content
    $columns = ['A' => 5, 'B' => 25, 'C' => 20, 'D' => 20, 'E' => 15, 'F' => 15, 'G' => 20, 'H' => 18, 'I' => 15, 'J' => 20];
    foreach ($columns as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // Add a thicker border at the bottom of the header row
    $sheet->getStyle('A1:J1')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
}

public function columnFormats(): array
{
    return [
        'F' => '#,##0.00', // Format for Harga (Rp)
        'G' => '#,##0.00', // Format for Harga Sebelumnya (Rp)
        'H' => '#,##0.00', // Format for Selisih Harga
        'I' => '0.00%',     // Format for Perubahan Harga (Percentage)
        'J' => 'DD/MM/YYYY', // Format for Tanggal
    ];
} }