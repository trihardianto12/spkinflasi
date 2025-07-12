<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Link;
 // Pastikan ini mengacu pada model Link Anda

class FrontendController extends Controller
{
    public function index(Request $request) // Menambahkan parameter Request untuk mengambil input pencarian
    {
        $search = $request->get('search'); // Mengambil nilai 'search' dari query string
        
        // Memulai query dasar dengan scope 'approved'
        $baseQuery = Link::approved(); 

        // Menerapkan filter pencarian jika ada nilai 'search'
        if ($search) {
            $baseQuery->where(function($q) use ($search) {
                $q->where('title', 'LIKE', '%' . $search . '%')
                 ;
            });
        }
        
        // Mengambil berita terbaru
        // Menggunakan clone $baseQuery agar query ini tidak memengaruhi query untuk otherNews
        $latestNews = (clone $baseQuery)
                            ->orderBy('published_at', 'desc')
                            ->take(12) // Mengambil 12 berita terbaru sesuai permintaan Anda
                            ->get();
        
        // Mengambil berita lainnya
        // Menggunakan clone $baseQuery lagi untuk memastikan query yang terpisah
        $otherNews = (clone $baseQuery)
                            ->orderBy('published_at', 'desc')
                            ->skip(3) // Melewati 3 berita pertama yang cocok dengan filter
                            ->take(5)  // Mengambil 5 berita berikutnya setelah yang dilewati
                            ->get();

        return view('frontend.news', compact('latestNews', 'otherNews'));
    }
}
