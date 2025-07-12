<?php

namespace App\Http\Controllers;

use App\Models\Penilaian;

class PenilaianController extends Controller
{
    public function show($id)
    {
        $penilaian = Penilaian::findOrFail($id);

        $score = $penilaian->calculateMautScore();           // Hitung skor MAUT
        $rank = $penilaian->calculateGlobalRanking();        // Hitung ranking global

        return view('penilaian.show', compact('penilaian', 'score', 'rank'));
    }
}
