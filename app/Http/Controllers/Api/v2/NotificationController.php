<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogs;
use Illuminate\Http\Request;

class NotificationController extends Controller
{

    public function __construct()
    {
    }

    public function notificationLogs(Request $request)
    {
        $user = $request->user();
        $logs = NotificationLogs::where('user_id', $user->id)
            ->with('fromUser')
            ->orderBy('created_at', 'DESC')->get();

        $response = array(
            'error' => false,
            'message' => 'User notifications successfully fetched',
            'data' => $logs,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function notificationRead(Request $request)
    {
        $user = $request->user();
        $logs = NotificationLogs::where('id', $request->notification_id)
            ->where('user_id', $user->id)->update([
                'read' => 1
            ]);

        if (!$logs) {
            $response = array(
                'error' => true,
                'message' => 'User notifications failed to update',
                'data' => $logs,
                'code' => 400
            );
        } else {
            $response = array(
                'error' => false,
                'message' => 'User notifications successfully updated',
                'data' => $logs,
                'code' => 200
            );
        }
        return response()->json($response);

    }

}