<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Entity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveEntity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $entity = null;

        $requestedEntityId = $request->header('X-Entity-ID')
            ?? $request->query('entity_id');
        $requestedEntityReference = $request->header('X-Entity-Reference')
            ?? $request->query('entity_reference');
        $requestedEntitySubdomain = $request->header('X-Entity-Subdomain')
            ?? $request->query('subdomain');
        $requestedEntityDomain = $request->header('X-Entity-Domain')
            ?? $request->query('domain');

        if ($requestedEntityId) {
            $requestedEntityId = (int) $requestedEntityId;
        }

        if ($user instanceof \App\Models\Manager) {
            $entity = $user->currentLink()->with('entity')->first()?->entity;
        } elseif ($user instanceof \App\Models\User) {
            $entity = $user->card?->entity ?? $user->card()->with('entity')->first()?->entity;
        }

        if (!$entity && $requestedEntityReference) {
            $entity = Entity::whereRaw('LOWER(reference) = ?', [mb_strtolower(trim((string) $requestedEntityReference))])->first();
        }

        if (!$entity && $requestedEntitySubdomain) {
            $entity = Entity::whereRaw('LOWER(subdomain) = ?', [mb_strtolower(trim((string) $requestedEntitySubdomain))])->first();
        }

        if (!$entity && $requestedEntityDomain) {
            $entity = Entity::whereHas('domain', function ($query) use ($requestedEntityDomain) {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $requestedEntityDomain))]);
            })->first();
        }

        if (!$entity && $requestedEntityId) {
            $entity = Entity::find($requestedEntityId);
        }

        if ($entity) {
            $request->attributes->set('current_entity', $entity);
            $request->attributes->set('current_entity_id', $entity->id);
        }

        return $next($request);
    }
}
