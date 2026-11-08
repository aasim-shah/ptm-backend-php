<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** The reader has read a chat; tells the other participant (read receipts). */
class MessagesRead implements ShouldBroadcastNow
{
    public function __construct(public string $chatId, public int $readerId, public int $otherUserId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.user.{$this->otherUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    public function broadcastWith(): array
    {
        return ['chat_id' => $this->chatId, 'reader_id' => $this->readerId];
    }
}
