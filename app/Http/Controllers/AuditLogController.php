<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vault;
use App\Support\AuditActions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAuditLog', Vault::class);

        $action = $request->string('action')->toString() ?: null;
        $userId = $request->integer('user_id') ?: null;
        $subjectUserId = $request->integer('subject_user_id') ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $query = Activity::query()
            ->where('log_name', AuditActions::LOG_NAME)
            ->with('causer:id,name,email')
            ->orderByDesc('id');

        if ($action && in_array($action, AuditActions::ALL, true)) {
            $query->where('event', $action);
        }
        if ($userId) {
            $query->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', $userId);
        }
        if ($subjectUserId) {
            $query->where('subject_type', (new User)->getMorphClass())
                ->where('subject_id', $subjectUserId);
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $entries = $query->limit(200)->get()->map(fn (Activity $a) => [
            'id' => $a->id,
            'action' => $a->event,
            'action_label' => AuditActions::LABELS[$a->event] ?? $a->event,
            'description' => $a->description,
            'properties' => $a->properties?->toArray() ?? [],
            'subject_type' => $a->subject_type ? class_basename((string) $a->subject_type) : null,
            'subject_id' => $a->subject_id,
            'user' => $a->causer ? [
                'id' => $a->causer->id,
                'name' => $a->causer->name,
                'email' => $a->causer->email,
            ] : null,
            'created_at' => $a->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Audit/Log', [
            'entries' => $entries,
            'overview' => [
                'count' => $entries->count(),
                'limit' => 200,
            ],
            'filters' => [
                'action' => $action && in_array($action, AuditActions::ALL, true) ? $action : null,
                'user_id' => $userId,
                'subject_user_id' => $subjectUserId,
                'from' => $from,
                'to' => $to,
            ],
            'actions' => collect(AuditActions::ALL)->map(fn ($key) => [
                'value' => $key,
                'label' => AuditActions::LABELS[$key] ?? $key,
            ])->values(),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }
}
