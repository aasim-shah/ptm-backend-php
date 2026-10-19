<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Services\CallUsageService;
use App\Support\LoginResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallUsageController extends Controller
{
    /**
     * POST /update-remaining-time  { "used_time": <seconds> }
     *
     * The app reports how long the call lasted. Only time the server itself observed for
     * the caller's call sessions, and not yet charged, is deducted (see CallUsageService).
     * Returns the LoginResponse plus the refreshed subscription.
     */
    public function updateRemainingTime(Request $request, CallUsageService $usage)
    {
        $validator = Validator::make($request->all(), [
            'used_time' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 422,
            ], 422);
        }

        $user = $request->user();
        $charged = $usage->chargeFromApp($user->id, (int) $request->input('used_time'));

        return LoginResponse::make($user->refresh(), 'Remaining time updated', [
            'charged_seconds' => $charged,
            'subscription' => UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')->first(),
        ]);
    }
}
