<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\InputData;

class Map extends Component
{
    public function render()
    {
        $inputdata = InputData::all();
        return view('livewire.map', [
            'inputdata' => $inputdata
        ]);
    }
}