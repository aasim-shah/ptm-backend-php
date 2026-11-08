<?php

namespace App\Events;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A new chat message, pushed to both participants' private channels. */
class MessageSent implements ShouldBroadcastNow
{
    public function __construct(public ChatMessage $chatMessage)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.user.{$this->chatMessage->sender_id}"),
            new PrivateChannel("chat.user.{$this->chatMessage->receiver_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatMessage->chat_id,
            'message' => (new ChatMessageResource($this->chatMessage))->resolve(),
        ];
    }
}
