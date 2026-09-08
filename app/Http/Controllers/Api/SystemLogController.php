<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SystemLogController extends Controller
{
    /**
     * Obtenir la liste filtrée et paginée des logs Laravel.
     */
    public function index(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => [],
                    'total' => 0,
                    'summary' => [
                        'error' => 0,
                        'warning' => 0,
                        'info' => 0,
                        'debug' => 0,
                    ],
                ],
            ]);
        }

        $levelFilter = strtolower($request->input('level', 'all'));
        $search = strtolower(trim($request->input('search', '')));
        $limit = (int) $request->input('limit', 150);

        // Lecture optimisée du fichier journal
        $content = File::get($logPath);
        
        // Regex de découpage des entrées de log Laravel standard: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d*:?\d*)\]\s+([\w\.\-]+)\.([A-Z]+):\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\z)/ms';
        
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $parsedLogs = [];
        $counts = [
            'error' => 0,
            'warning' => 0,
            'info' => 0,
            'debug' => 0,
            'critical' => 0,
            'emergency' => 0,
            'alert' => 0,
            'notice' => 0,
        ];

        // Parcourir de l'entrée la plus récente à la plus ancienne
        for ($i = count($matches) - 1; $i >= 0; $i--) {
            $match = $matches[$i];
            $timestamp = $match[1];
            $environment = $match[2];
            $level = strtolower($match[3]);
            $message = trim($match[4]);

            if (isset($counts[$level])) {
                $counts[$level]++;
            }

            // Normalisation pour le filtre UI
            $category = $level;
            if (in_array($level, ['critical', 'emergency', 'alert'])) {
                $category = 'error';
            } elseif ($level === 'notice') {
                $category = 'info';
            }

            if ($levelFilter !== 'all' && $category !== $levelFilter && $level !== $levelFilter) {
                continue;
            }

            if ($search !== '' && !str_contains(strtolower($message), $search) && !str_contains(strtolower($timestamp), $search)) {
                continue;
            }

            $parsedLogs[] = [
                'id' => md5($timestamp . $message . $i),
                'timestamp' => $timestamp,
                'environment' => $environment,
                'level' => strtoupper($level),
                'category' => $category,
                'message' => $message,
            ];

            if (count($parsedLogs) >= $limit) {
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $parsedLogs,
                'total' => count($parsedLogs),
                'summary' => [
                    'error' => $counts['error'] + $counts['critical'] + $counts['emergency'] + $counts['alert'],
                    'warning' => $counts['warning'],
                    'info' => $counts['info'] + $counts['notice'],
                    'debug' => $counts['debug'],
                ],
            ],
        ]);
    }

    /**
     * Vider le fichier journal Laravel.
     */
    public function clear()
    {
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        return response()->json([
            'success' => true,
            'message' => 'Journal de logs nettoyé avec succès !',
        ]);
    }
}
