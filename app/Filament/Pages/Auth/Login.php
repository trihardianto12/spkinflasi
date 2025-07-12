<?php

namespace App\Filament\Pages\Auth; // Perhatikan namespace ini!

use Filament\Pages\Auth\Login as BaseLogin;
use DiogoGPinto\AuthUIEnhancer\Pages\Auth\Concerns\HasCustomLayout; // Import trait ini

class Login extends BaseLogin // Sesuaikan nama kelas ini jika Anda menamainya berbeda
{
    use HasCustomLayout; // Gunakan trait ini

  
}