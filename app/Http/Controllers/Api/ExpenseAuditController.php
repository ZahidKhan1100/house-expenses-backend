<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseAuditLog;
use Illuminate\Http\Request;

class ExpenseAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $house = $user->house;

        if (!$house) {
            return response()->json([
                'currency' => '$',
                'logs' => [],
            ]);
        }

        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $rows = ExpenseAuditLog::query()
            ->where('house_id', $house->id)
            ->with(['actor' => static function ($q) {
                $q->withTrashed()->select('id', 'name');
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $logs = $rows->map(function (ExpenseAuditLog $log) {
            $actor = $log->actor;

            return [
                'id' => $log->id,
                'action' => $log->action,
                'summary' => $log->summary,
                'actor_name' => $actor?->name ?? 'Someone',
                'actor_user_id' => (int) $log->actor_user_id,
                'record_id' => $log->record_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'currency' => $house->currency ?? '$',
            'logs' => $logs,
        ]);
    }
}
