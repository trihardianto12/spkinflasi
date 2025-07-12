<?php 
namespace App\Filament\Widgets;

use App\Models\InputData;
use Webbingbrasil\FilamentMaps\Actions;
use Webbingbrasil\FilamentMaps\Marker;
use Webbingbrasil\FilamentMaps\Widgets\MapWidget;

class Map extends MapWidget
{
    public function getHeading(): string
{
    return 'Peta Lokasi';
}

    protected static ?int $sort = 1;




    
    protected int | string | array $columnSpan = 2;
    
    public function getMarkers(): array
    {
        $locations = InputData::select('latitude', 'longitude', 'nama_komoditas', 'harga')->get();

        return $locations->map(function ($location, $index) {
            return Marker::make('marker_' . $index)
                ->lat($location->latitude)
                ->lng($location->longitude)
                ->popup(
                    'Nama Komoditas: ' . $location->nama_komoditas . '<br>' .
                    'Harga: ' . $location->harga
                );
        })->toArray();
    }

    public function getActions(): array
    {
        return [
            Actions\ZoomAction::make(),
            Actions\CenterMapAction::make()->centerTo([-2.9866186610501777, 104.76025374066234])->zoom(13),
        ];
    }
}