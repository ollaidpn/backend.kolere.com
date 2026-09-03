<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BackofficeNotificationController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);
            $manager = $request->user();

            if (!$entityId || !$manager) {
                return response()->json(['data' => []]);
            }

            $notifications = Notification::query()
                ->where('entity_id', $entityId)
                ->whereNull('user_id')
                ->where(function ($query) use ($manager) {
                    $query->whereNull('manager_id')
                        ->orWhere('manager_id', $manager->id);
                })
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $readIds = NotificationRead::query()
                ->where('manager_id', $manager->id)
                ->whereIn('notification_id', $notifications->pluck('id'))
                ->pluck('notification_id')
                ->all();

            return response()->json([
                'data' => $notifications->map(function (Notification $notification) use ($readIds) {
                    $row = $notification->toArray();
                    $row['is_read'] = in_array($notification->id, $readIds, true);
                    return $row;
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('[BackofficeNotificationController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des notifications'], 500);
        }
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);
            $manager = $request->user();

            if (!$entityId || !$manager) {
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            $notification = Notification::query()
                ->where('id', $id)
                ->where('entity_id', $entityId)
                ->whereNull('user_id')
                ->where(function ($query) use ($manager) {
                    $query->whereNull('manager_id')
                        ->orWhere('manager_id', $manager->id);
                })
                ->firstOrFail();

            NotificationRead::updateOrCreate(
                [
                    'notification_id' => $notification->id,
                    'manager_id' => $manager->id,
                ],
                [
                    'read_at' => Carbon::now(),
                ]
            );

            return response()->json(['message' => 'Notification marquée comme lue.']);
        } catch (\Exception $e) {
            Log::error('[BackofficeNotificationController@markRead] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }
}
