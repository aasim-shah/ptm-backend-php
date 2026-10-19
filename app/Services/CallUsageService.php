<?php

namespace App\Services;

use App\Models\CallUsageLedger;
use App\Models\UserCallRoom;
use App\Models\UserSubscription;
use App\Paypal\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Server-owned call minutes (Doc 12 §8).
 *
 * Every charge is tied to a call session in user_call_room and limited to what has not
 * been charged for that session yet (charged_seconds), so the Agora webhook and the app's
 * POST /update-remaining-time can both report the same call without billing it twice.
 * Each charge is written to the insert-only call_usage_ledger.
 */
class CallUsageService
{
    /**
     * Agora leave event: Agora's duration for the session is authoritative.
     */
    public function chargeFromWebhook(UserCallRoom $room, int $sessionDuration): int
    {
        $seconds = max(0, $sessionDuration - (int) $room->charged_seconds);
        return $this->charge($room, $seconds, 'agora_webhook');
    }

    /**
     * App report (POST /update-remaining-time). The client value is never trusted on its
     * own: it is capped at the call time the server observed between /join/call and
     * /left/call (or now, for a call still in progress) that is not charged yet.
     *
     * @return int seconds charged
     */
    public function chargeFromApp(int $userId, int $reportedSeconds): int
    {
        $remaining = max(0, $reportedSeconds);
        if ($remaining === 0) {
            return 0;
        }

        $charged = 0;
        $rooms = UserCallRoom::where('user_id', $userId)->whereNotNull('joined_at')
            ->orderBy('joined_at')->get();
        foreach ($rooms as $room) {
            if ($remaining <= 0) {
                break;
            }
            $observed = $this->observedSeconds($room) - (int) $room->charged_seconds;
            if ($observed <= 0) {
                continue;
            }
            $seconds = min($observed, $remaining);
            $charged += $this->charge($room, $seconds, 'app_report', $reportedSeconds);
            $remaining -= $seconds;
        }
        return $charged;
    }

    /** Seconds between the server-seen join and leave (or now if still joined). */
    public function observedSeconds(UserCallRoom $room): int
    {
        if (!$room->joined_at) {
            return 0;
        }
        $joined = Carbon::parse($room->joined_at);
        $end = $room->left_at ? Carbon::parse($room->left_at) : Carbon::now();
        return max(0, $end->getTimestamp() - $joined->getTimestamp());
    }

    private function charge(UserCallRoom $room, int $seconds, string $source, ?int $reported = null): int
    {
        if ($seconds <= 0) {
            return 0;
        }
        DB::transaction(function () use ($room, $seconds, $source, $reported) {
            $room->charged_seconds = (int) $room->charged_seconds + $seconds;
            $room->save();
            CallUsageLedger::create([
                'user_id' => $room->user_id,
                'user_call_room_id' => $room->id,
                'channel_name' => $room->chanel_name,
                'seconds' => $seconds,
                'client_reported_seconds' => $reported,
                'source' => $source,
            ]);
        });
        $this->deductSubscription($room->user_id, $seconds);
        return $seconds;
    }

    /** Moved from WebhookController::inactivateSubscription. */
    public function deductSubscription($userId, int $seconds): void
    {
        $isActive = UserSubscription::where('user_id', $userId)
            ->where('status', 'ACTIVE')->where('remaining_time', '!=', 0)->first();
        if (!$isActive) {
            return;
        }
        $remainingTime = $isActive->remaining_time - $seconds;
        $isActive->remaining_time = max($remainingTime, 0);
        if ($remainingTime <= 0) {
            Log::info("reduce call remaining_time by $seconds s for user $userId");
            $isActive->status = 'INACTIVE';
            $isActive->call_count = 0;
        }
        $isActive->save();
        $isActive = $isActive->refresh();

        if ($isActive->status == 'INACTIVE' && $isActive->subscription_id) {
            try {
                Log::info("inactivating paypal subscription for subscription_id " . $isActive->subscription_id);
                (new SubscriptionPlan())->cancelSubscription($isActive->subscription_id, true, [
                    "reason" => "Inactivate automatically due to 0 remaining time"
                ]);
            } catch (\Throwable $e) {
                Log::error($e);
            }
        }
    }
}
