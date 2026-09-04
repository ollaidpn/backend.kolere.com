<?php

namespace App\Console\Commands;

use App\Models\Entity;
use App\Models\EntityMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MailCreateCommand extends Command
{
    protected $signature = 'mail:create {--entity= : Entity ID or reference} {--action= : requests|mails}';

    protected $description = 'Gère les demandes et les boîtes email de boutiques';

    public function handle(): int
    {
        $entity = $this->resolveEntity($this->option('entity'));
        if (!$entity) {
            $entity = $this->chooseEntity();
        }

        if (!$entity) {
            $this->error('Aucune boutique sélectionnée.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Boutique sélectionnée: ' . $entity->name . ' (' . $entity->reference . ')');
        $this->line('Domaine: ' . ($entity->domain?->name ? '@' . ltrim((string) $entity->domain->name, '@') : 'non configuré'));
        $this->newLine();

        while (true) {
            $action = $this->option('action');
            if (!$action) {
                $action = $this->choice('Que veux-tu faire ?', [
                    'Voir les demandes',
                    'Voir les mails créés',
                    'Quitter',
                ], 0);
            } else {
                $action = match ($action) {
                    'requests' => 'Voir les demandes',
                    'mails' => 'Voir les mails créés',
                    default => 'Quitter',
                };
            }

            if ($action === 'Quitter') {
                $this->info('Fin de la commande.');
                return self::SUCCESS;
            }

            if ($action === 'Voir les demandes') {
                $this->handleRequests($entity);
            } elseif ($action === 'Voir les mails créés') {
                $this->handleCreatedMails($entity);
            }

            if ($this->option('action')) {
                return self::SUCCESS;
            }
        }
    }

    private function chooseEntity(): ?Entity
    {
        $entities = Entity::query()
            ->with('domain')
            ->orderBy('name')
            ->get();

        if ($entities->isEmpty()) {
            return null;
        }

        $choices = $entities->mapWithKeys(function (Entity $entity) {
            $label = sprintf(
                '%s | %s | %s',
                $entity->name,
                $entity->reference ?? ('#' . $entity->id),
                $entity->domain?->name ? '@' . ltrim((string) $entity->domain->name, '@') : 'sans domaine'
            );

            return [$label => $entity->id];
        })->all();

        $selectedId = $this->choice('Choisis la boutique', array_keys($choices), 0);
        $entityId = $choices[$selectedId] ?? null;

        return $entityId ? $entities->firstWhere('id', $entityId) : null;
    }

    private function resolveEntity(mixed $value): ?Entity
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Entity::query()->with('domain')->find((int) $value);
        }

        return Entity::query()
            ->with('domain')
            ->where('reference', (string) $value)
            ->orWhere('subdomain', (string) $value)
            ->first();
    }

    private function handleRequests(Entity $entity): void
    {
        $requests = EntityMail::query()
            ->where('entity_id', $entity->id)
            ->where('status', 'requested')
            ->orderByDesc('created_at')
            ->get();

        if ($requests->isEmpty()) {
            $this->warn('Aucune demande en attente pour cette boutique.');
            return;
        }

        $this->table(
            ['ID', 'Adresse', 'Username', 'Suffixe', 'Statut', 'Mot de passe'],
            $requests->map(function (EntityMail $mail) {
                return [
                    $mail->id,
                    $mail->email_address,
                    $mail->username,
                    $mail->at_domain,
                    $mail->status,
                    $mail->password ? 'oui' : 'non',
                ];
            })->all()
        );

        $selected = $this->selectMailFromCollection($requests, 'Sélectionne une demande à compléter');
        if (!$selected) {
            return;
        }

        $this->fillRequest($selected);
    }

    private function handleCreatedMails(Entity $entity): void
    {
        $mails = EntityMail::query()
            ->where('entity_id', $entity->id)
            ->where('status', '!=', 'requested')
            ->orderByDesc('created_at')
            ->get();

        if ($mails->isEmpty()) {
            $this->warn('Aucune boîte déjà créée pour cette boutique.');
            return;
        }

        $this->table(
            ['ID', 'Adresse', 'Statut', 'Host', 'Serveur', 'Webmail'],
            $mails->map(function (EntityMail $mail) {
                return [
                    $mail->id,
                    $mail->email_address,
                    $mail->status,
                    $mail->host ?? '-',
                    $mail->server ?? '-',
                    $mail->webmail_link ?? '-',
                ];
            })->all()
        );

        $selected = $this->selectMailFromCollection($mails, 'Sélectionne une boîte pour voir le détail');
        if (!$selected) {
            return;
        }

        $this->showMailDetail($selected);
    }

    private function selectMailFromCollection(Collection $mails, string $question): ?EntityMail
    {
        $options = $mails->mapWithKeys(function (EntityMail $mail) {
            return [sprintf('#%d | %s', $mail->id, $mail->email_address) => $mail->id];
        })->all();

        $selectedLabel = $this->choice($question, array_keys($options), 0);
        $selectedId = $options[$selectedLabel] ?? null;

        return $selectedId ? $mails->firstWhere('id', $selectedId) : null;
    }

    private function fillRequest(EntityMail $mail): void
    {
        $this->info('Compléter la demande: ' . $mail->email_address);

        $host = $this->askWithDefault('Host', $mail->host ?: 'mail.' . ltrim((string) $mail->at_domain, '@'));
        $server = $this->askWithDefault('Serveur', $mail->server ?: $host);
        $webmail = $this->askWithDefault('Lien webmail', $mail->webmail_link ?: 'https://');
        $setPassword = $this->confirm('Définir un mot de passe maintenant ?', (bool) $mail->password);
        $password = $setPassword
            ? $this->secretWithDefault('Mot de passe', $mail->password ?: '')
            : null;
        $status = $this->choice('Statut final', ['active', 'suspended', 'requested'], $mail->status === 'requested' ? 0 : 0);

        $mail->update([
            'host' => $host,
            'server' => $server,
            'webmail_link' => $webmail,
            'password' => $password,
            'status' => $status,
            'activated_at' => $status === 'active' ? ($mail->activated_at ?: Carbon::now()) : $mail->activated_at,
        ]);

        $this->newLine();
        $this->info('Demande mise à jour.');
        $this->line('Adresse: ' . $mail->refresh()->email_address);
        $this->line('Statut: ' . $mail->status);
    }

    private function showMailDetail(EntityMail $mail): void
    {
        $this->newLine();
        $this->info('Détail de la boîte: ' . $mail->email_address);
        $this->line('Status: ' . $mail->status);
        $this->line('Host: ' . ($mail->host ?: '-'));
        $this->line('Serveur: ' . ($mail->server ?: '-'));
        $this->line('Webmail: ' . ($mail->webmail_link ?: '-'));
        $this->line('Mot de passe: ' . ($mail->password ?: '-'));

        if ($this->confirm('Veux-tu modifier cette boîte ?', false)) {
            $this->fillRequest($mail);
        }
    }

    private function askWithDefault(string $question, string $default): string
    {
        $answer = $this->ask($question, $default);
        return trim((string) $answer);
    }

    private function secretWithDefault(string $question, string $default): string
    {
        $answer = $this->secret($question);
        $answer = trim((string) $answer);

        if ($answer === '' && $default !== '') {
            return $default;
        }

        return $answer;
    }
}
