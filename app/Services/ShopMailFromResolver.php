<?php

namespace App\Services;

use App\Models\Entity;
use Illuminate\Http\Request;

class ShopMailFromResolver
{
    public function resolve(?Entity $entity = null, ?Request $request = null): array
    {
        $entity ??= $this->resolveEntity($request);

        if (! $entity) {
            $entity = Entity::query()
                ->whereNotNull('email')
                ->orderBy('id')
                ->first() ?? Entity::query()->orderBy('id')->first();
        }

        $address = $entity?->email ?: config('mail.from.address');
        $name = $entity?->name ?: config('mail.from.name');

        return [
            'address' => $address,
            'name' => $name,
            'entity' => $entity,
        ];
    }

    public function applyTo(callable $callback, ?Entity $entity = null, ?Request $request = null): void
    {
        $from = $this->resolve($entity, $request);

        $callback($from['address'], $from['name']);
    }

    private function resolveEntity(?Request $request = null): ?Entity
    {
        if (! $request) {
            return null;
        }

        $entityId = $request->attributes->get('current_entity_id')
            ?: $request->header('X-Entity-ID')
            ?: $request->query('entity_id');

        if (is_numeric($entityId)) {
            return Entity::query()->find((int) $entityId);
        }

        $reference = $request->attributes->get('current_entity_reference')
            ?: $request->header('X-Entity-Reference')
            ?: $request->query('entity_reference');

        if (is_string($reference) && trim($reference) !== '') {
            return Entity::query()
                ->whereRaw('LOWER(reference) = ?', [mb_strtolower(trim($reference))])
                ->first();
        }

        return null;
    }
}
