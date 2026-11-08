<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one chat message shape used by chat/send, the history endpoint and the
 * message.sent broadcast. Times are ISO-8601 UTC ("...Z"); the app converts them.
 */
class ChatMessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (int) $this->id,
            'chat_id' => $this->chat_id,
            'sender_id' => (int) $this->sender_id,
            'receiver_id' => (int) $this->receiver_id,
            'message' => $this->message,
            'file_url' => $this->fileUrl(),
            'file_extension' => $this->file_extension,
            'is_image' => (bool) $this->is_image,
            'created_at' => $this->created_at?->toISOString(),
            'read_at' => $this->read_at?->toISOString(),
        ];
    }
}
