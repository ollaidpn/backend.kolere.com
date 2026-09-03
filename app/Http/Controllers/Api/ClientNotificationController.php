<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ClientNotificationController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);
            $user = $request->user();

            if (!$entityId || !$user) {
                return response()->json(['data' => []]);
            }

            $notifications = Notification::query()
                ->where('entity_id', $entityId)
                ->where(function ($query) use ($user) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $user->id);
                })
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $readIds = NotificationRead::query()
                ->where('user_id', $user->id)
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
            Log::error('[ClientNotificationController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des notifications'], 500);
        }
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);
            $user = $request->user();

            if (!$entityId || !$user) {
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            $notification = Notification::query()
                ->where('id', $id)
                ->where('entity_id', $entityId)
                ->where(function ($query) use ($user) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $user->id);
                })
                ->firstOrFail();

            NotificationRead::updateOrCreate(
                [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ],
                [
                    'read_at' => Carbon::now(),
                ]
            );

            return response()->json(['message' => 'Notification marquée comme lue.']);
        } catch (\Exception $e) {
            Log::error('[ClientNotificationController@markRead] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }
}
