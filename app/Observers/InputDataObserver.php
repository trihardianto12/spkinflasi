<?php
namespace App\Observers;

use App\Models\InputData;
use App\Models\DataAlternatif;

class InputDataObserver
{
    /**
     * Saat data input_data ditambahkan, otomatis buat data di data_alternatif.
     */
    public function created(InputData $inputData)
    {
        DataAlternatif::create([
            'input_data_id' => $inputData->id,
            'nama_komoditas' => $inputData->nama_komoditas,
            'harga' => $inputData->harga,
            'tanggal' => $inputData->tanggal,
        ]);
    }

    /**
     * Saat data input_data diperbarui, otomatis update data di data_alternatif.
     */
    public function updated(InputData $inputData)
    {
        $alternatif = DataAlternatif::where('input_data_id', $inputData->id)->first();

        if ($alternatif) {
            $alternatif->update([
                'nama_komoditas' => $inputData->nama_komoditas,
                'harga' => $inputData->harga,
                'tanggal' => $inputData->tanggal,
            ]);
        }
    }

    /**
     * Saat data input_data dihapus, otomatis hapus data di data_alternatif.
     */
    public function deleted(InputData $inputData)
    {
        DataAlternatif::where('input_data_id', $inputData->id)->delete();
    }
}
