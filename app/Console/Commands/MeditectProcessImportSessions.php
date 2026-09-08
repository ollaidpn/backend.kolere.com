<?php

namespace App\Console\Commands;

use App\Services\MeditectService;
use Illuminate\Console\Command;

class MeditectProcessImportSessions extends Command
{
    protected $signature = 'meditect:process-imports';

    protected $description = 'Traite les sessions Meditect en arrière-plan par lots.';

    public function handle(MeditectService $meditectService): int
    {
        \Illuminate\Support\Facades\Log::info('[MeditectCron] Démarrage du traitement de la file meditect:process-imports...');
        $results = $meditectService->processPendingMeditectImportSessions();

        if (empty($results)) {
            $this->info('Aucune session Meditect à traiter.');
            \Illuminate\Support\Facades\Log::info('[MeditectCron] Aucune session en cours d\'exécution (session state non démarré ou terminée).');
            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $message = $result['message'] ?? 'Session traitée.';
            $this->info($message);
            \Illuminate\Support\Facades\Log::info('[MeditectCron] Résultat du lot: ' . json_encode($result));
        }

        return self::SUCCESS;
    }
}
