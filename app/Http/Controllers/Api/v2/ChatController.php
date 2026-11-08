<?php

namespace App\Http\Controllers\Api\v2;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMessageResource;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Schools;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Services\PushNotificationSender;
use App\Services\RoleContextService;
use App\Support\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xlsx', 'ppt', 'pptx', 'txt'];

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function teacherChat()
    {
        $to_user = request()->to_user;

        if (!Access::actsAsParent(Auth::user())) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }
        $sender_id = Auth::user()->id;
        // With a role context the chat list is limited to that context's school.
        $schoolIds = Access::schoolIds(Auth::user())
            ?? UserSchools::where('user_id', $sender_id)->pluck('school_id')->toArray();

        if (!$schoolIds) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_school_assigned')
            ], 400);
        }
        // Teachers, principals and staff/admins of the parent's schools.
        $userIds = app(RoleContextService::class)->institutionUserIds($schoolIds);

//        $school = Schools::where('id',$school->school_id)->first();

//        if($school->principal_id){
//            array_push($userIds,$school->principal_id);
//        }



        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        if ($to_user_id) {

            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id);
                    $query->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id);
                    $query->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if($chats){
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatExists = Chat::where(function ($query) use ($sender_id, $to_user_id, $userIds) {
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->whereIn('user_id', $userIds);
                $query->orWhereIn('to_user_id', $userIds);
            });
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->where('user_id', $sender_id);
                $query->orWhere('to_user_id', $sender_id);
            });
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        $response = [
            'chat_users' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats
        ];
        return response()->json($response);

    }

    public function parentChat()
    {
        $to_user = request()->to_user;
        $role = Access::role(Auth::user());
        if (in_array($role, ['staff', 'school_admin', 'district_admin'], true)) {
            return $this->institutionChat($to_user);
        }
        if ($role != 'teacher') {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }
        $sender_id = Auth::user()->id;
        $contextSchoolIds = Access::schoolIds(Auth::user());
        $school = $contextSchoolIds
            ? UserSchools::where('user_id', $sender_id)->where('school_id', $contextSchoolIds[0])->first()
            : UserSchools::where('user_id', $sender_id)->first();
        if (!$school) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_school_assigned')
            ], 400);
        }
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'parent');
        })->where('school_id', $school->school_id)->pluck('user_id')->toArray();

        $school = Schools::where('id',$school->school_id)->first();

        if($school->principal_id){
            array_push($userIds,$school->principal_id);
        }

        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        if ($to_user_id) {

            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id);
                    $query->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id);
                    $query->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if($chats){
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatExists = Chat::where(function ($query) use ($sender_id, $to_user_id, $userIds) {
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->whereIn('user_id', $userIds);
                $query->orWhereIn('to_user_id', $userIds);
            });
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->where('user_id', $sender_id);
                $query->orWhere('to_user_id', $sender_id);
            });
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'parent');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'parent');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        $response = [
            'chat_users' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats
        ];

        return response()->json($response);
    }

    /**
     * POST chat/send: stores the message (and file) in MySQL, updates the chat summary row
     * used by the chat lists, then pushes it to both participants over Reverb.
     */
    public function createChat(Request $request){
        $file = $request->file('file');
        $file_extension = $file
            ? strtolower(ltrim((string) ($request->file_extension ?: $file->getClientOriginalExtension()), '.'))
            : null;
        $validator = Validator::make($request->all() + ['extension' => $file_extension], [
            'to_user_id' => 'required|integer',
            'message' => 'nullable|string|required_without:file',
            'file' => 'nullable|file|max:10240',
            'extension' => 'nullable|in:' . implode(',', self::FILE_EXTENSIONS),
        ], [
            'message.required_without' => 'A message or a file is required.',
            'file.max' => 'The file may not be larger than 10 MB.',
            'extension.in' => 'This file type is not allowed. Allowed: ' . implode(', ', self::FILE_EXTENSIONS) . '.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $user = Auth::user();
        $to_user_id = $request->to_user_id;
        $receiver = User::where('id',$to_user_id)->first();
        if(!$receiver){
            return response()->json([
                'status' => 400,
                'message' => "Invalid user detected",
                'data' => [
                    "to_user_id" => $to_user_id
                ]
            ], 400);
        }
        if (!$this->mayMessage($user, $receiver)) {
            return Access::forbidden();
        }

        $storedPath = null;
        try {
            DB::beginTransaction();
            $chatExists = Chat::where(function ($query) use ($user,$receiver){
                $query->where('user_id',$user->id);
                $query->where('to_user_id',$receiver->id);
            })->orWhere(function ($query) use ($user,$receiver){
                $query->where('user_id',$receiver->id);
                $query->where('to_user_id',$user->id);
            })->lockForUpdate()->first();
            $chatId = $chatExists ? $chatExists->id : Utility::getUUID();

            $is_image = false;
            if ($file) {
                $storedPath = $file->storeAs("chat/{$chatId}", Str::uuid() . '.' . $file_extension, 'public');
                $is_image = in_array($file_extension, ChatMessage::IMAGE_EXTENSIONS, true);
            }
            $fileUrl = $storedPath ? url(Storage::disk('public')->url($storedPath)) : null;

            $chatJson =
                [
                    'message' => $request->message,
                    'sender_id' => $user->id,
                    'file' => $fileUrl,
                    'file_extension' => $file_extension,
                    'is_image' => $is_image,
                    'sender_name' => $user->first_name,
                    'receiver_name' => $receiver->first_name,
                    'created_at' => Carbon::now()
                ];

            if(!$chatExists){
                $chat = new Chat();
                $chat->id = $chatId;
                $chat->user_id = $user->id;
                $chat->to_user_id = $to_user_id;
                $chat->new_message = true;
                $chat->message = json_encode($chatJson,true);
                $chat->save();
            }else{
                if(($chatExists->message['sender_id'] ?? null) == $user->id){
                    $chatExists->new_message_count = $chatExists->new_message_count+1;
                }else{
                    $chatExists->new_message_count = 1;
                }
                $chatExists->new_message = true;
                $chatExists->message = $chatJson;
                $chatExists->updated_at = Carbon::now();
                $chatExists->save();
            }

            $chatMessage = ChatMessage::create([
                'chat_id' => $chatId,
                'sender_id' => $user->id,
                'receiver_id' => $receiver->id,
                'message' => $request->message,
                'file_path' => $storedPath,
                'file_extension' => $file_extension,
                'is_image' => $is_image,
            ]);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            Log::error($exception);
            return response()->json(['status' => 500, 'message' => trans('error_occurred')], 500);
        }

        // Realtime is best effort: when Reverb is down the app falls back to polling the history.
        try {
            broadcast(new MessageSent($chatMessage));
        } catch (\Throwable $exception) {
            Log::warning('Chat broadcast failed', ['chat_id' => $chatId, 'error' => $exception->getMessage()]);
        }
        $this->brodCastMessage($user,$request->message,$to_user_id,$chatJson);

        return response()->json($chatJson + [
            'chat_id' => $chatId,
            'chat_message' => (new ChatMessageResource($chatMessage))->resolve(),
        ]);
    }

    /**
     * GET chat/{chat_id}/messages?limit=50&before_id=: one page of history, oldest first.
     * Without before_id it is the newest page; has_more says whether older messages exist.
     */
    public function messages(Request $request, $chatId)
    {
        $user = Auth::user();
        $chat = Chat::where('id', $chatId)->first();
        if (!$chat) {
            return response()->json(['message' => 'Chat not found.'], 404);
        }
        if (!in_array((int) $user->id, [(int) $chat->user_id, (int) $chat->to_user_id], true)) {
            return Access::forbidden();
        }

        $limit = (int) $request->query('limit', 50);
        $limit = $limit < 1 ? 50 : min($limit, 100);
        $beforeId = $request->query('before_id');

        $page = ChatMessage::where('chat_id', $chat->id)
            ->when(is_numeric($beforeId), function ($query) use ($beforeId) {
                $query->where('id', '<', (int) $beforeId);
            })
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $page->count() > $limit;

        return response()->json([
            'data' => ChatMessageResource::collection($page->take($limit)->reverse()->values())->resolve(),
            'has_more' => $hasMore,
        ]);
    }

    public function chatRead(Request $request){
        $chat_id = $request->id;
        $user = Auth::user();

        $isChat = Chat::where('id',$chat_id)
            ->where(function ($query) use ($user){
                $query->where('user_id',$user->id);
                $query->orWhere('to_user_id',$user->id);
            })
            ->first();
        if(!$isChat){
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }

        if(($isChat->message['sender_id'] ?? null) != $user->id){
            $isChat->new_message_count = 0;
            $isChat->save();
        }

        $read = ChatMessage::where('chat_id', $isChat->id)
            ->where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
        if ($read > 0) {
            $otherUserId = (int) $isChat->user_id === (int) $user->id ? (int) $isChat->to_user_id : (int) $isChat->user_id;
            try {
                broadcast(new MessagesRead($isChat->id, (int) $user->id, $otherUserId));
            } catch (\Throwable $exception) {
                Log::warning('Chat read broadcast failed', ['chat_id' => $isChat->id, 'error' => $exception->getMessage()]);
            }
        }
        return response()->json($isChat->refresh());

    }
    /**
     * GET /teacher/chat for staff and admin contexts: the caller's conversations with
     * people of the active organization. Same response shape as the teacher list; an empty
     * list when there are none.
     */
    private function institutionChat($to_user_id)
    {
        $user = Auth::user();
        $sender_id = $user->id;
        $userIds = app(RoleContextService::class)->userIdsInSchools(Access::schoolIds($user) ?? []);
        $userIds = array_values(array_diff($userIds, [(int) $sender_id]));

        $chats = null;
        $to_user = null;
        if ($to_user_id && in_array((int) $to_user_id, $userIds, true)) {
            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id)->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id)->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if ($chats) {
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatUsers = Chat::where(function ($query) use ($userIds) {
            $query->whereIn('user_id', $userIds ?: [0])->orWhereIn('to_user_id', $userIds ?: [0]);
        })->where(function ($query) use ($sender_id) {
            $query->where('user_id', $sender_id)->orWhere('to_user_id', $sender_id);
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id)->whereIn('id', $userIds ?: [0]);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id)->whereIn('id', $userIds ?: [0]);
        }])->orderBy('updated_at', 'DESC')->get();

        return response()->json([
            'chat_users' => $chatUsers,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats,
        ]);
    }

    /**
     * Relationship rule for sending (Part A6). Without a role context the legacy behaviour
     * is kept. With one, the receiver must belong to the context's organization, and a
     * parent may only message the institution side (teachers, principals, staff, admins).
     */
    private function mayMessage(User $sender, User $receiver): bool
    {
        $schoolIds = Access::schoolIds($sender);
        if ($schoolIds === null) {
            return true;
        }
        $contexts = app(RoleContextService::class);
        if (Access::actsAsParent($sender)) {
            return in_array((int) $receiver->id, $contexts->institutionUserIds($schoolIds), true);
        }
        return in_array((int) $receiver->id, $contexts->userIdsInSchools($schoolIds), true);
    }

    private function brodCastMessage($user,$message,$to_user_id,$chatJson){

        $message =  $message ?? "New File";
        $senderName = $user->first_name.' '.$user->last_name;
        $data = [
            'sender_name' => $senderName,
            'message' => $message,
            'body' => $chatJson
        ];

        // A push failure must never fail the chat send.
        try {
            $tokenUser = User::where('id',$to_user_id)->where('enable_notification',1)->first();

            if($tokenUser){
                PushNotificationSender::sendMobileMessage('New Message From : '.$senderName, $message, $tokenUser->fcm_id, "CHAT", $data);
                $this->notificationSender->saveLogs($to_user_id,$user->id,$message,'CHAT','New Message From : '.$senderName);

            }
        } catch (\Throwable $exception) {
            Log::warning('Chat push notification failed', ['to_user_id' => $to_user_id, 'error' => $exception->getMessage()]);
        }

    }

}