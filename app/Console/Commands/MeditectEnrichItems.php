<?php

namespace App\Console\Commands;

use App\Services\MeditectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MeditectEnrichItems extends Command
{
    protected $signature = 'meditect:enrich-items {--batch=30 : Nombre de produits à enrichir par exécution} {--force : Forcer le ré-enrichissement de tous les produits Meditect}';

    protected $description = 'Enrichit les produits Meditect en attente (catégories, marques, détails) par appel API.';

    public function handle(MeditectService $meditectService): int
    {
        $batchSize = (int) $this->option('batch');
        $force = (bool) $this->option('force');

        $this->info("Démarrage de l'enrichissement Meditect (batch={$batchSize}, force=" . ($force ? 'oui' : 'non') . ")...");
        $this->newLine();

        $stats = $meditectService->enrichPendingMeditectItems($batchSize, $force, function (string $message, string $level = 'info') {
            if ($level === 'error') {
                $this->error("  ✖ " . $message);
            } elseif ($level === 'warn') {
                $this->warn("  ⚠ " . $message);
            } else {
                $this->info("  ✔ " . $message);
            }
        });

        $this->newLine();
        $this->table(
            ['Total Traités', 'Réussis', 'Ignorés', 'Échoués'],
            [[$stats['total'], $stats['success'], $stats['ignored'], $stats['failed']]]
        );

        Log::info("[MeditectEnrichCron] Traitement terminé: Total={$stats['total']}, Réussis={$stats['success']}, Ignorés={$stats['ignored']}, Échoués={$stats['failed']}");

        return self::SUCCESS;
    }
}
