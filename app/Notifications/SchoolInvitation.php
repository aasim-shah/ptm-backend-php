<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class SchoolInvitation extends Notification implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeEncrypted
{
    use Queueable;
    public $tries = 5;
    public $backoff = [60,300,900,3600];
    public function __construct(public string $link, public string $school) { $this->afterCommit(); $this->onConnection('workspace'); $this->onQueue('workspace'); }
    public function via($notifiable) { return ['mail']; }
    public function toMail($notifiable)
    {
        return (new MailMessage)->subject('Your PTM school invitation')->greeting('You are invited to Parent Teacher Mobile')
            ->line('A school administrator has invited you to '.$this->school.'.')->line('Use your existing PTM account or securely activate a new account. No password has been created or emailed for you.')
            ->action('Review invitation',$this->link)->line('The invitation expires in 72 hours. If you were not expecting it, you can ignore it.');
    }
}
