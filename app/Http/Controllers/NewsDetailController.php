<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\View\View;

class NewsDetailController extends Controller
{
    public function show($id)
{
    $link = Link::findOrFail($id);
    
    // Ambil berita lainnya (contoh: 4 berita terbaru kecuali yang sedang dilihat)
    $otherNews = Link::where('id', '!=', $id)
                    ->latest()
                    ->take(4)
                    ->get();
    
    return view('frontend.news.detail', compact('link', 'otherNews'));
}
}