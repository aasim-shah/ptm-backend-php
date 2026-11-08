<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\ChatMessage;
use Carbon\Carbon;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off copy of the old chat history from Firestore (messages/{chatId}/chatMessages)
 * into chat_messages. Uses the Firestore REST API, so the grpc extension is not needed.
 * Idempotent: a chat that already has rows in chat_messages is skipped.
 */
class ImportFirestoreChat extends Command
{
    protected $signature = 'chat:import-firestore {--chat= : Import only this chat id} {--dry-run : Count without writing}';
    protected $description = 'Copy the old Firestore chat history into chat_messages (skips chats that already have messages)';

    public function handle()
    {
        $keyFile = config('services.firebase.key_file');
        if (!is_file($keyFile)) {
            $this->error('Firebase service account key not found.');
            return self::FAILURE;
        }
        $project = config('environment.FIREBASE_PROJECT_ID') ?: (json_decode(file_get_contents($keyFile), true)['project_id'] ?? null);
        $token = (new ServiceAccountCredentials('https://www.googleapis.com/auth/datastore', $keyFile))->fetchAuthToken()['access_token'] ?? null;
        if (!$project || !$token) {
            $this->error('Could not authenticate with Firestore.');
            return self::FAILURE;
        }
        $http = new Client(['timeout' => 30, 'http_errors' => false, 'headers' => ['Authorization' => "Bearer $token"]]);
        $base = "https://firestore.googleapis.com/v1/projects/$project/databases/(default)/documents/messages";

        $chats = Chat::query()->when($this->option('chat'), fn ($q, $id) => $q->where('id', $id))->get();
        $imported = $skipped = 0;
        foreach ($chats as $chat) {
            if (ChatMessage::where('chat_id', $chat->id)->exists()) {
                $skipped++;
                continue;
            }
            $documents = [];
            $pageToken = null;
            do {
                $response = $http->get("$base/{$chat->id}/chatMessages", ['query' => array_filter(['pageSize' => 300, 'pageToken' => $pageToken])]);
                if ($response->getStatusCode() !== 200) {
                    $this->warn("Chat {$chat->id}: Firestore returned HTTP {$response->getStatusCode()}, skipped.");
                    continue 2;
                }
                $page = json_decode((string) $response->getBody(), true);
                $documents = array_merge($documents, $page['documents'] ?? []);
                $pageToken = $page['nextPageToken'] ?? null;
            } while ($pageToken);

            $rows = collect($documents)->map(fn ($doc) => $this->row($chat, $doc))->filter()->sortBy('created_at')->values();
            if ($rows->isEmpty()) {
                continue;
            }
            if (!$this->option('dry-run')) {
                DB::transaction(fn () => ChatMessage::insert($rows->all()));
            }
            $imported += $rows->count();
            $this->line("Chat {$chat->id}: {$rows->count()} messages");
        }
        $this->info(($this->option('dry-run') ? '[dry run] ' : '') . "Imported $imported messages; skipped $skipped chats that already had history.");
        return self::SUCCESS;
    }

    private function row(Chat $chat, array $doc): ?array
    {
        $fields = $this->decode(['mapValue' => ['fields' => $doc['fields'] ?? []]]);
        $data = $fields['data'] ?? [];
        $participants = [(int) $chat->user_id, (int) $chat->to_user_id];
        $senderId = (int) ($data['sender_id'] ?? 0);
        if (!in_array($senderId, $participants, true)) {
            return null;
        }
        $receiverId = (int) ($fields['to_user_id'] ?? 0);
        if (!in_array($receiverId, $participants, true) || $receiverId === $senderId) {
            $receiverId = $participants[0] === $senderId ? $participants[1] : $participants[0];
        }
        $file = $data['file'] ?? null;
        $createdAt = $data['created_at'] ?? $doc['createTime'] ?? null;
        $createdAt = $createdAt ? Carbon::parse($createdAt)->utc() : Carbon::now();

        return [
            'chat_id' => $chat->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            // "New File" was the placeholder text for file-only messages.
            'message' => array_key_exists('message', $data) ? $data['message'] : ($fields['text'] ?? null),
            // Old Firebase URLs are kept as-is; a bare name pointed at the old local folder.
            'file_path' => $file ? (Str::startsWith($file, ['http://', 'https://']) ? $file : 'chat/files/' . $file) : null,
            'file_extension' => isset($data['file_extension']) ? Str::limit((string) $data['file_extension'], 16, '') : null,
            'is_image' => (bool) ($data['is_image'] ?? false),
            'read_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /** Firestore typed value -> plain PHP value. */
    private function decode(array $value)
    {
        foreach ($value as $type => $inner) {
            switch ($type) {
                case 'mapValue':
                    return collect($inner['fields'] ?? [])->map(fn ($v) => $this->decode($v))->all();
                case 'arrayValue':
                    return collect($inner['values'] ?? [])->map(fn ($v) => $this->decode($v))->all();
                case 'integerValue':
                    return (int) $inner;
                case 'doubleValue':
                    return (float) $inner;
                case 'nullValue':
                    return null;
                default:
                    return $inner;
            }
        }
        return null;
    }
}
