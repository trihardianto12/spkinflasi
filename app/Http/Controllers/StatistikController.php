<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Link; 
use App\Models\InputData; 


class StatistikController extends Controller
{
    public function index()
    {
         $latestNews = Link::approved()
                        ->orderBy('published_at', 'desc')
                        ->take(3)
                        ->get();

        $otherNews = Link::approved()
                      ->orderBy('published_at', 'desc')
                      ->skip(3)
                      ->take(5)
                      ->get();

     
            return view('frontend.statistik', compact('latestNews', 'otherNews'));
    }
}

