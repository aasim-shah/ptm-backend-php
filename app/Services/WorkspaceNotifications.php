<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkspaceNotifications
{
    public const TITLES = ['message' => 'New school message', 'update' => 'Student progress update', 'appointment' => 'Meeting update', 'notice' => 'School announcement', 'event' => 'School event'];
    public const PAGES = ['message' => 'messages', 'update' => 'updates', 'appointment' => 'schedule', 'notice' => 'notices', 'event' => 'events'];

    public function __construct(private WorkspaceAccess $access, private WorkspaceDashboard $dashboard) {}

    /** Must be called inside the domain transaction: outbox work survives queue outages. */
    public function record(int $schoolId, string $kind, int $resourceId, string $eventKey, array $recipients): void
    {
        if (!isset(self::TITLES[$kind])) { throw new \InvalidArgumentException('Unsupported notification kind.'); }
        foreach (array_unique($recipients) as $userId) {
            DB::table('workspace_notifications')->insertOrIgnore(['school_id' => $schoolId, 'kind' => $kind, 'resource_id' => $resourceId,
                'event_key' => $eventKey, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            $notification = DB::table('workspace_notifications')->where('user_id', $userId)->where('event_key', $eventKey)->first();
            foreach (['email', 'push'] as $channel) {
                if (config('delivery.'.$channel.'_enabled')) {
                    DB::table('workspace_deliveries')->insertOrIgnore(['notification_id' => $notification->id, 'channel' => $channel,
                        'status' => 'pending', 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function audience(int $schoolId, string $audience = 'all'): array
    {
        $ids = DB::table('workspace_memberships')->where('school_id', $schoolId)->where('status', 'active')
            ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); });
        if ($audience !== 'all') { $ids->where('role', $audience); }
        $result = $ids->pluck('user_id');
        if (in_array($audience, ['all', 'district_admin'])) {
            $districts = DB::table('workspace_district_schools')->where('school_id', $schoolId)->pluck('district_id');
            $result = $result->merge(DB::table('workspace_memberships')->whereIn('district_id', $districts)->where('role', 'district_admin')->where('status', 'active')
                ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
                ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })->pluck('user_id'));
        }
        return $result->unique()->all();
    }

    public function link(User $user, $notification): ?string
    {
        if (!$user->status || !$user->email_verified_at) { return null; }
        foreach ($this->access->contexts($user) as $context) {
            if (!array_key_exists($notification->school_id, $context['schools'])) { continue; }
            $allowed = match ($notification->kind) {
                'message' => $this->dashboard->messages($user, $context)->where('m.id', $notification->resource_id)->exists(),
                'appointment' => $this->dashboard->appointments($user, $context)->where('id', $notification->resource_id)->exists(),
                'update' => $this->dashboard->updates($user, $context)->where('u.id', $notification->resource_id)->where('u.status', 'published')->exists(),
                'notice', 'event' => $this->dashboard->posts($context, $notification->kind)->where('id', $notification->resource_id)->exists(),
                default => false,
            };
            if ($allowed) { return route('workspace.page', ['page' => self::PAGES[$notification->kind], 'context' => $context['id']]); }
        }
        return null;
    }
}
