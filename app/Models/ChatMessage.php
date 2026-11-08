<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One chat message. file_path is a path on the public disk, or an absolute URL for
 * messages imported from the old Firebase storage.
 */
class ChatMessage extends Model
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    protected $fillable = [
        'chat_id', 'sender_id', 'receiver_id', 'message', 'file_path', 'file_extension', 'is_image', 'read_at',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'is_image' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /** Absolute URL of the attached file, or null. */
    public function fileUrl(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }
        return url(Storage::disk('public')->url($this->file_path));
    }
}
