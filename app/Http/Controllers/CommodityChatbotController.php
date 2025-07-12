<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InputData;
use App\Models\DataAlternatif;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class CommodityChatbotController extends Controller
{
    // Daftar perintah yang dikenali
    private $commands = [
        '/start' => 'showWelcome',
        '/help' => 'showHelp',
        '/harga' => 'checkPrice',
        '/lokasi' => 'checkLocation',
        '/trend' => 'priceTrend',
        '/inflasi' => 'inflationRate',
        '/bandingkan' => 'comparePrices',
        '/daftar' => 'listCommodities',
        '/pasar' => 'listMarkets'
    ];

    // Handler utama untuk request chatbot
    public function handleRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'user_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->formatResponse('Format request tidak valid', false);
        }

        $message = strtolower(trim($request->input('message')));
        $userId = $request->input('user_id', 'guest');

        Log::info("Chatbot request from {$userId}: {$message}");

        // Cek perintah yang dikenali
        foreach ($this->commands as $cmd => $method) {
            if (strpos($message, $cmd) === 0) {
                return $this->{$method}($message, $userId);
            }
        }

        return $this->unknownCommand($userId);
    }

    // ==================== FUNGSI UTAMA CHATBOT ====================

    /**
     * Menampilkan pesan selamat datang
     */
    private function showWelcome($message, $userId)
    {
        $response = "🤖 *Sistem Pemantauan Harga Komoditas*\n\n";
        $response .= "Selamat datang! Saya akan membantu memantau harga komoditas.\n\n";
        $response .= "Ketik /help untuk melihat daftar perintah yang tersedia.";

        return $this->formatResponse($response);
    }

    /**
     * Menampilkan daftar perintah bantuan
     */
    private function showHelp($message, $userId)
    {
        $response = "📋 *Daftar Perintah*\n\n";
        $response .= "• /start - Memulai bot\n";
        $response .= "• /help - Menampilkan bantuan\n";
        $response .= "• /daftar - Daftar komoditas tersedia\n";
        $response .= "• /pasar - Daftar lokasi pasar\n";
        $response .= "• /harga [komoditas] - Cek harga terbaru\n";
        $response .= "• /lokasi [komoditas] [pasar] - Cek harga di lokasi\n";
        $response .= "• /trend [komoditas] - Trend harga 7 hari\n";
        $response .= "• /inflasi [komoditas] - Laju inflasi bulanan\n";
        $response .= "• /bandingkan [komoditas1] [komoditas2] - Bandingkan harga\n\n";
        $response .= "Contoh: /harga beras, /trend minyak goreng, /lokasi gula pasar induk";

        return $this->formatResponse($response);
    }

    /**
     * Mengecek harga komoditas terbaru
     */
    private function checkPrice($message, $userId)
    {
        $komoditas = trim(str_replace('/harga', '', $message));
        
        if (empty($komoditas)) {
            return $this->formatResponse("Silakan sertakan nama komoditas. Contoh: /harga beras", false);
        }

        // Cari 3 hasil teratas untuk handle typo
        $results = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas%")
                    ->orderBy('tanggal', 'desc')
                    ->take(3)
                    ->get();

        if ($results->isEmpty()) {
            return $this->formatResponse("Data harga untuk '{$komoditas}' tidak ditemukan", false);
        }

        // Jika ditemukan hasil tepat
        if ($results->count() == 1 || $results[0]->nama_komoditas == $komoditas) {
            $data = $results[0];
            return $this->formatPriceResponse($data);
        }

        // Jika ada beberapa hasil
        $response = "🔍 Ditemukan beberapa hasil untuk '{$komoditas}':\n";
        foreach ($results as $index => $item) {
            $response .= "\n" . ($index + 1) . ". " . ucfirst($item->nama_komoditas) . 
                         " - Rp " . number_format($item->harga, 0, ',', '.');
        }
        $response .= "\n\nSilakan perjelas pencarian Anda.";

        return $this->formatResponse($response);
    }

    /**
     * Mengecek harga di lokasi tertentu
     */
    private function checkLocation($message, $userId)
    {
        $parts = explode(' ', str_replace('/lokasi', '', $message));
        $parts = array_values(array_filter(array_map('trim', $parts)));
        
        if (count($parts) < 2) {
            return $this->formatResponse(
                "Format: /lokasi [komoditas] [nama pasar]. Contoh: /lokasi beras pasar induk", 
                false
            );
        }

        $komoditas = $parts[0];
        $lokasi = implode(' ', array_slice($parts, 1));

        $data = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas%")
                    ->where('lokasi_pasar', 'like', "%$lokasi%")
                    ->latestRecord()
                    ->first();

        if (!$data) {
            return $this->formatResponse(
                "Data harga {$komoditas} di {$lokasi} tidak ditemukan", 
                false
            );
        }

        $response = "📍 *Harga {$data->nama_komoditas} di {$data->lokasi_pasar}*\n";
        $response .= "Harga: Rp " . number_format($data->harga, 0, ',', '.') . "\n";
        $response .= "Satuan: {$data->satuan}\n";
        $response .= "Perubahan: {$data->perbedaan_harga_tertata}\n";
        $response .= "Pasokan: {$data->tingkat_pasokan}\n";
        $response .= "Pemasok: {$data->asal_pemasok}\n";
        $response .= "Update: " . $data->tanggal->format('d M Y');

        return $this->formatResponse($response);
    }

    // ==================== FUNGSI ANALISIS DATA ====================

    /**
     * Menampilkan trend harga 7 hari terakhir
     */
    private function priceTrend($message, $userId)
    {
        $komoditas = trim(str_replace('/trend', '', $message));
        
        if (empty($komoditas)) {
            return $this->formatResponse(
                "Silakan sertakan nama komoditas. Contoh: /trend beras", 
                false
            );
        }

        $data = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas%")
                    ->where('tanggal', '>=', Carbon::now()->subDays(7))
                    ->orderBy('tanggal', 'asc')
                    ->get();

        if ($data->isEmpty()) {
            return $this->formatResponse(
                "Data trend harga untuk {$komoditas} tidak ditemukan", 
                false
            );
        }

        $commodityName = $data[0]->nama_komoditas;
        $response = "📈 *Trend Harga {$commodityName} (7 Hari Terakhir)*\n\n";
        
        foreach ($data as $item) {
            $response .= "➡️ " . $item->tanggal->format('d M') . ": ";
            $response .= "Rp " . number_format($item->harga, 0, ',', '.');
            $response .= " ({$item->perbedaan_harga_tertata})\n";
        }

        // Hitung perubahan mingguan
        $first = $data->first()->harga;
        $last = $data->last()->harga;
        $change = $last - $first;
        $percent = $first != 0 ? ($change / $first) * 100 : 0;
        
        $trend = $change >= 0 ? "↑" : "↓";
        $response .= "\n*Perubahan Mingguan*: {$trend} " . number_format(abs($percent), 2) . "%";

        return $this->formatResponse($response);
    }

    /**
     * Menghitung laju inflasi bulanan
     */
    private function inflationRate($message, $userId)
    {
        $komoditas = trim(str_replace('/inflasi', '', $message));
        
        if (empty($komoditas)) {
            return $this->formatResponse(
                "Silakan sertakan nama komoditas. Contoh: /inflasi beras", 
                false
            );
        }

        $current = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas%")
                    ->latestRecord()
                    ->first();

        $lastMonth = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas%")
                    ->where('tanggal', '>=', Carbon::now()->subDays(30))
                    ->orderBy('tanggal', 'asc')
                    ->first();

        if (!$current || !$lastMonth) {
            return $this->formatResponse(
                "Data inflasi untuk {$komoditas} tidak lengkap", 
                false
            );
        }

        $change = $current->harga - $lastMonth->harga;
        $percent = $lastMonth->harga != 0 ? ($change / $lastMonth->harga) * 100 : 0;
        
        $trend = $change >= 0 ? "↑" : "↓";
        $category = $this->getInflationCategory($percent);
        
        $response = "📊 *Laju Inflasi {$current->nama_komoditas}*\n\n";
        $response .= "Harga 30 Hari Lalu: Rp " . number_format($lastMonth->harga, 0, ',', '.') . "\n";
        $response .= "Harga Sekarang: Rp " . number_format($current->harga, 0, ',', '.') . "\n";
        $response .= "Perubahan: {$trend} " . number_format(abs($percent), 2) . "%\n";
        $response .= "Klasifikasi: {$category}\n\n";
        $response .= "Lokasi: {$current->lokasi_pasar}\n";
        $response .= "Update: " . $current->tanggal->format('d M Y');

        return $this->formatResponse($response);
    }

    // ==================== FUNGSI TAMBAHAN ====================

    /**
     * Membandingkan harga dua komoditas
     */
    private function comparePrices($message, $userId)
    {
        $parts = explode(' ', str_replace('/bandingkan', '', $message));
        $parts = array_values(array_filter(array_map('trim', $parts)));
        
        if (count($parts) < 2) {
            return $this->formatResponse(
                "Format: /bandingkan [komoditas1] [komoditas2]. Contoh: /bandingkan beras gula", 
                false
            );
        }

        $komoditas1 = $parts[0];
        $komoditas2 = $parts[1];

        $data1 = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas1%")
                    ->latestRecord()
                    ->first();

        $data2 = DataAlternatif::where('nama_komoditas', 'like', "%$komoditas2%")
                    ->latestRecord()
                    ->first();

        if (!$data1 || !$data2) {
            return $this->formatResponse(
                "Data komoditas tidak lengkap untuk perbandingan", 
                false
            );
        }

        $response = "⚖️ *Perbandingan Harga*\n\n";
        $response .= "1. {$data1->nama_komoditas}: Rp " . number_format($data1->harga, 0, ',', '.') . "\n";
        $response .= "   Lokasi: {$data1->lokasi_pasar}\n";
        $response .= "   Trend: {$data1->perbedaan_harga_tertata}\n\n";
        
        $response .= "2. {$data2->nama_komoditas}: Rp " . number_format($data2->harga, 0, ',', '.') . "\n";
        $response .= "   Lokasi: {$data2->lokasi_pasar}\n";
        $response .= "   Trend: {$data2->perbedaan_harga_tertata}\n\n";
        
        $difference = $data1->harga - $data2->harga;
        if ($difference > 0) {
            $response .= "🔺 " . ucfirst($data1->nama_komoditas) . " lebih mahal Rp " . 
                         number_format(abs($difference), 0, ',', '.');
        } elseif ($difference < 0) {
            $response .= "🔻 " . ucfirst($data2->nama_komoditas) . " lebih mahal Rp " . 
                         number_format(abs($difference), 0, ',', '.');
        } else {
            $response .= "Harga keduanya sama";
        }

        return $this->formatResponse($response);
    }

    /**
     * Menampilkan daftar komoditas yang tersedia
     */
    private function listCommodities($message, $userId)
    {
        $commodities = DataAlternatif::select('nama_komoditas')
                        ->distinct()
                        ->orderBy('nama_komoditas', 'asc')
                        ->take(20)
                        ->get()
                        ->pluck('nama_komoditas');

        if ($commodities->isEmpty()) {
            return $this->formatResponse("Tidak ada data komoditas tersedia", false);
        }

        $response = "📜 *Daftar Komoditas Tersedia*\n\n";
        $response .= implode("\n", $commodities->map(function($item) {
            return "• " . ucfirst($item);
        })->toArray());
        
        $response .= "\n\nGunakan /harga [nama] untuk cek harga";

        return $this->formatResponse($response);
    }

    /**
     * Menampilkan daftar pasar/lokasi yang tersedia
     */
    private function listMarkets($message, $userId)
    {
        $markets = DataAlternatif::select('lokasi_pasar')
                    ->distinct()
                    ->orderBy('lokasi_pasar', 'asc')
                    ->take(20)
                    ->get()
                    ->pluck('lokasi_pasar');

        if ($markets->isEmpty()) {
            return $this->formatResponse("Tidak ada data pasar tersedia", false);
        }

        $response = "🏪 *Daftar Pasar/Lokasi Tersedia*\n\n";
        $response .= implode("\n", $markets->map(function($item) {
            return "• " . ucfirst($item);
        })->toArray());
        
        $response .= "\n\nGunakan /lokasi [komoditas] [pasar] untuk cek harga";

        return $this->formatResponse($response);
    }

    // ==================== FUNGSI PENDUKUNG ====================

    /**
     * Menangani perintah yang tidak dikenali
     */
    private function unknownCommand($userId)
    {
        $response = "Perintah tidak dikenali. Ketik /help untuk melihat daftar perintah yang tersedia.";
        return $this->formatResponse($response, false);
    }

    /**
     * Klasifikasi tingkat inflasi
     */
    private function getInflationCategory($percent)
    {
        if ($percent >= 10) return '📛 Inflasi Tinggi';
        if ($percent >= 5) return '⚠️ Inflasi Sedang';
        if ($percent >= 1) return '🔸 Inflasi Rendah';
        if ($percent <= -1) return '✅ Deflasi';
        return '➖ Stabil';
    }

    /**
     * Format respons harga komoditas
     */
    private function formatPriceResponse($data)
    {
        $response = "💰 *Harga {$data->nama_komoditas}*\n";
        $response .= "Lokasi: {$data->lokasi_pasar}\n";
        $response .= "Harga: Rp " . number_format($data->harga, 0, ',', '.') . "/{$data->satuan}\n";
        $response .= "Perubahan: {$data->perbedaan_harga_tertata}\n";
        $response .= "Klasifikasi: {$data->laju_inflasi}\n";
        $response .= "Terakhir update: " . $data->tanggal->format('d M Y');

        return $this->formatResponse($response);
    }

    /**
     * Format respons standar
     */
    private function formatResponse($text, $success = true)
    {
        return response()->json([
            'success' => $success,
            'text' => $text,
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}