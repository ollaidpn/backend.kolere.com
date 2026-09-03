<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $platform = $request->header('X-App-Platform', 'ios');
        $currentVersion = $request->header('X-App-Version', '0.0.0');

        $config = config('app_versions');
        $platformConfig = $config[$platform] ?? $config['ios'];
        $versions = $platformConfig['versions'] ?? [];

        // Dernière version = dernière entrée du tableau
        $latest = end($versions);
        $latestVersion = $latest ? $latest['version'] : '1.0.0';
        $latestChangelog = $latest ? $latest['changelog'] : '';

        // Trouver la version must_update la plus haute
        $mustUpdateVersion = '0.0.0';
        foreach ($versions as $entry) {
            if (!empty($entry['must_update']) && version_compare($entry['version'], $currentVersion, '>')) {
                if (version_compare($entry['version'], $mustUpdateVersion, '>')) {
                    $mustUpdateVersion = $entry['version'];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'latest_version' => $latestVersion,
                'must_update_version' => $mustUpdateVersion,
                'changelog' => $latestChangelog,
            ],
        ]);
    }
}
