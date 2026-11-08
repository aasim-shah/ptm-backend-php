<?php

namespace App\Console\Commands;

use App\Services\WorkspaceNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Sends notifications for announcements and events whose scheduled publish time has arrived. */
class PublishScheduledPosts extends Command
{
    protected $signature = 'workspace:publish-scheduled';
    protected $description = 'Notify recipients of scheduled announcements and events that are now due';

    public function handle(WorkspaceNotifications $notifications): int
    {
        $sent = 0;
        DB::table('workspace_posts')->whereNotNull('publish_at')->whereNull('notified_at')->where('publish_at', '<=', now())
            ->orderBy('id')->limit(200)->get()->each(function ($post) use ($notifications, &$sent) {
                DB::transaction(function () use ($post, $notifications, &$sent) {
                    // Claim the row first so two scheduler runs never notify twice.
                    if (!DB::table('workspace_posts')->where('id', $post->id)->whereNull('notified_at')->update(['notified_at' => now()])) { return; }
                    $active = DB::table('schools')->where('id', $post->school_id)->where('status', 'active')->exists();
                    if ($active) { $notifications->record((int) $post->school_id, $post->kind, $post->id, 'post:'.$post->id, $notifications->audience((int) $post->school_id, $post->audience)); }
                    $sent++;
                });
            });
        $this->info("Published {$sent} scheduled posts.");
        return self::SUCCESS;
    }
}
