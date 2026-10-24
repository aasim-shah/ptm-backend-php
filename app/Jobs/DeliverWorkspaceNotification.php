<?php
namespace App\Jobs;

use App\Models\User;
use App\Services\WorkspaceNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class DeliverWorkspaceNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1;
    public $timeout = 60;
    public function __construct(public int $deliveryId) { $this->onConnection('workspace'); $this->onQueue('workspace'); }

    public function handle(WorkspaceNotifications $notifications): void
    {
        $claimed = DB::table('workspace_deliveries')->where('id', $this->deliveryId)->where('status', 'pending')->where('attempts', '<', 5)->where('available_at', '<=', now())
            ->update(['status' => 'sending', 'locked_at' => now(), 'attempts' => DB::raw('attempts + 1')]);
        if (!$claimed) { return; }
        $delivery = DB::table('workspace_deliveries')->find($this->deliveryId);
        try {
            $notification = DB::table('workspace_notifications')->find($delivery->notification_id);
            $user = $notification ? User::find($notification->user_id) : null;
            $link = $user ? $notifications->link($user, $notification) : null;
            if (!$link || !config('delivery.'.$delivery->channel.'_enabled')) {
                DB::table('workspace_deliveries')->where('id', $delivery->id)->update(['status' => 'cancelled', 'updated_at' => now()]); return;
            }
            $title = WorkspaceNotifications::TITLES[$notification->kind];
            $body = 'You have a school update. Open Parent Teacher Mobile to view it securely.';
            if ($delivery->channel === 'email') {
                $email = app()->environment('production') ? $user->email : config('delivery.test_email');
                if (!$email) { throw new \RuntimeException('NonproductionDeliveryNotConfigured'); }
                Mail::raw($body."\n\n".$link, function ($message) use ($email, $title) { $message->to($email)->subject($title); });
            } else {
                $devices = DB::table('workspace_devices')->where('user_id', $user->id)->whereNull('revoked_at');
                if (!app()->environment('production')) { $devices->where('token_hash', config('delivery.test_token_hash') ?? 'disabled'); }
                $devices = $devices->get();
                if ($devices->isEmpty()) { DB::table('workspace_deliveries')->where('id', $delivery->id)->update(['status'=>'cancelled','last_error'=>'NoActivePushDevice','updated_at'=>now()]); return; }
                foreach ($devices as $device) {
                    app('firebase.messaging')->send(CloudMessage::withTarget('token', Crypt::decryptString($device->token))
                        ->withNotification(Notification::create($title, $body))->withData(['url' => $link, 'notification_id' => (string) $notification->id]));
                }
            }
            DB::table('workspace_deliveries')->where('id', $delivery->id)->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
        } catch (\Throwable $error) {
            DB::table('workspace_deliveries')->where('id', $delivery->id)->update(['status' => $delivery->attempts >= 5 ? 'failed' : 'pending',
                'available_at' => now()->addSeconds(min(3600, 30 * (2 ** $delivery->attempts))),
                'last_error' => class_basename($error), 'updated_at' => now()]);
        }
    }
}
