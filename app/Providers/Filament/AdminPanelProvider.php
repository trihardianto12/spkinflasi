<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use App\Filament\Widgets\PetaLokasiPasar;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Widgets\MapWidget;
use App\Filament\Widgets\Map;
use App\Observers\InputDataObserver;
use Illuminate\View\View;
use Filament\Pages\Auth\YourLoginClass;
  use Carbon\Carbon;
use App\Filament\Pages\Auth\Login;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;

use Illuminate\Support\Facades\Route; // Pastikan ini diimpor
use App\Filament\Resources\HasilAkhirResource;
use DiogoGPinto\AuthUIEnhancer\Pages\Auth\Concerns\HasCustomLayout;
 


class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            
             ->favicon(asset('images/Logo_Dinas.svg'))
            // ->darkModebrandLogo(asset('images/Logo_Dinas2.svg') )
            ->brandLogo(fn (): View => view('filament.logo'))
            ->darkModebrandLogo(fn (): View => view('filament.tri'))
            ->id('admin')
            ->path('admin')
             ->login()
            
          
            ->profile()
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->sidebarCollapsibleOnDesktop()
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
               
                // Widgets\FilamentInfoWidget::class,
                
                // You can keep or remove this,
                Map::class
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
              
        // AuthUIEnhancerPlugin::make()
          
          
           
        //             ->formPanelPosition('right') 
              
        //             ->formPanelWidth('600em') // Lebar form
        //             ->formPanelBackgroundColor(Color::hex('#ffffff')) // Background form
        //             ->emptyPanelBackgroundColor(Color::hex('#f0f0f0')) // Background panel kosong (kanan)
        //             ->emptyPanelBackgroundImageUrl('https://images.pexels.com/photos/466685/pexels-photo-466685.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2')
        //             ->emptyPanelBackgroundImageOpacity('50%'),

    // (jika ada properti layoutDirection, tambahkan seperti ini:)
    // ->layoutDirection('row')

          
            ])
            ;
            
    }


    public function boot(): void
    {
        // Daftarkan rute kustom untuk ekspor PDF
        Route::get('/admin/hasil-akhirs/export-pdf', [HasilAkhirResource::class, 'exportPdf'])
            ->name('filament.admin.resources.hasil-akhirs.export-pdf')
            ->middleware(['web', 'auth']);
            
         Carbon::setLocale('id');   
          // Pastikan middleware sesuai dengan panel admin Anda
    }

    
}

