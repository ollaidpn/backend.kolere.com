<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LogViewerController extends Controller
{
    public function index(Request $request)
    {
        $logFile = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[\.\d]*[\+\d:]*)\]\s+(\w+)\.(\w+):\s+(.*?)(?=\[\d{4}-\d{2}-\d{2}[T ]|\z)/s';

            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $logs[] = [
                    'date'    => $match[1],
                    'env'     => $match[2],
                    'level'   => strtolower($match[3]),
                    'message' => trim($match[4]),
                ];
            }

            // Reverse to show newest first
            $logs = array_reverse($logs);
        }

        // Count by level
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        $counts = [];
        foreach ($levels as $level) {
            $counts[$level] = count(array_filter($logs, fn($l) => $l['level'] === $level));
        }
        $counts['all'] = count($logs);

        return view('logs', compact('logs', 'counts', 'levels'));
    }

    public function clear()
    {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        return redirect('/')->with('success', 'Logs vidés avec succès.');
    }
}
