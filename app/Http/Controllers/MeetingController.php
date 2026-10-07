<?php

namespace App\Http\Controllers;

use App\Class\AgoraDynamicKey\RtcTokenBuilder;
use App\Helpers\Utility;
use App\Models\CallDetails;
use App\Models\Chat;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\MainMeeting;
use App\Models\Meetings;
use App\Models\Principal;
use App\Models\Schools;
use App\Models\Students;
use App\Models\UserCallRoom;
use App\Models\UserSchools;
use App\Models\UserSubscription;
use App\Services\NotificationSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

use App\Events\MakeAgoraCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Mockery\Exception;
use function PHPUnit\Framework\isEmpty;

class MeetingController extends Controller
{
    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function index(Request $request)
    {
        // fetch all users apart from the authenticated user
        $senderId = $request->sender_id;
        $chats = Chat::with('user')->orderBy('created_at', 'ASC')->get();
        return view('agora-chat', ['chats' => $chats]);
    }

    public function calendarIndex(Request $request)
    {
        // fetch all users apart from the authenticated user
        $id = $request->id;
        $user_id = Auth::user()->id;

        $school = principalSchoolOrFail($user_id);
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'teacher');
        })->where('school_id', $school->id)->pluck('user_id')->toArray();
        $teachers = User::whereIn('id', $userIds)->get();

        return view('calendar', [
            'meetings' => [],
            'teachers' => $teachers,
        ]);
    }

    public function getMeetings($status = null)
    {

        try {
            $user = Auth::user();
            $meetings = Meetings::select('id', 'title', 'user_id', 'teacher_id', 'parent_id', 'meeting_date', 'meeting_time', 'meeting_end_time', 'description', 'student_id', 'meeting_hash')
                ->where(function ($query) use ($user) {
                    $query->where('parent_id', $user->id);
                    $query->orWhere('teacher_id', $user->id);
                    $query->orWhere('principal_id', $user->id);

                })
                ->with(['teacher' => function ($query) {
                    $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
                }])
                ->where(function ($query) use ($status) {
                    if ($status && $status != 'null') {
                        $query->where('status', $status);
                    }
                })
                ->orderBy('id', 'DESC')
                ->groupBy('meeting_hash')
                ->get();

            $meetingsStudents = Meetings::where(function ($query) use ($user) {
                $query->where('parent_id', $user->id);
                $query->orWhere('teacher_id', $user->id);
                $query->orWhere('principal_id', $user->id);
            })->where(function ($query) use ($status) {
                if ($status) {
                    $query->where('status', $status);
                }
            })->with('student.student.class_section.class')->groupBy('student_id')->get();
            $meetingsParents = Meetings::where(function ($query) use ($user) {
                $query->where('parent_id', $user->id);
                $query->orWhere('teacher_id', $user->id);
            })->where(function ($query) use ($status) {
                if ($status) {
                    $query->where('status', $status);
                }
            })->with(['parent' => function ($query) {
                $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
            }])->groupBy('student_id')->get();

            foreach ($meetings as $meeting) {
                $parents = [];

                foreach ($meetingsParents as $meetingsParent) {
                    $students = [];
                    if ($meeting->meeting_hash == $meetingsParent->meeting_hash && $meetingsParent->parent->id == $meeting->parent_id) {

                        foreach ($meetingsStudents as $meetingsStudent) {
                            if ($meetingsParent->meeting_hash == $meetingsStudent->meeting_hash
                                && ($meetingsStudent->student->student->father_id == $meetingsParent->parent->id || $meetingsStudent->student->student->mother_id == $meetingsParent->parent->id)) {
                                $students[] = $meetingsStudent->student;
                            }
                        }
                        $meetingsParent->parent->students = $students;
                    }
                    $parents[] = $meetingsParent->parent;
                }
                $meeting->_parents = $parents;
            }
            return $meetings;


        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }


    public function teacherChat($to_user = null)
    {

        if (Auth::user()->type != 'Principal') {
            return redirect()->route('home')->withErrors(['message' => 'Chat is available to principals only.']);
        }
        $sender_id = Auth::user()->id;
        $school = principalSchoolOrFail($sender_id);
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'teacher');
        })->where('school_id', $school->id)->pluck('user_id')->toArray();

        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        $chatId = null;

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
                $chatId = $chats->id;
//                $chats->new_message = false;
//                $chats->updated_at = $chats->updated_at;
//                $chats->save();
            }else{
                $chatId = Utility::getUUID();
                $chats = $this->createChat(Auth::user(),$chatId,$to_user_id);
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
            $query->where('type', 'teacher');
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where('type', 'teacher');
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        return view('chat', [
            'route' => 'teacher',
            'chats' => $chats,
            'chatId' => $chatId,
            'history' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
        ]);
    }

    public function parentChat($to_user = null)
    {
        if (Auth::user()->type != 'Principal') {
            return redirect()->route('home')->withErrors(['message' => 'Chat is available to principals only.']);
        }
        $sender_id = Auth::user()->id;
        $school = principalSchoolOrFail($sender_id);
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'parent');
        })->where('school_id', $school->id)->pluck('user_id')->toArray();

        $to_user_id = $to_user;
        $chats = null;
        $chatId = null;
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
                $chatId = $chats->id;
//                $chats->updated_at = $chats->updated_at;
//                $chats->new_message = false;
//                $chats->save();
            }else{
                $chatId = Utility::getUUID();
                $chats = $this->createChat(Auth::user(),$chatId,$to_user_id);
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
            $query->where('type', 'parent');
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where('type', 'parent');
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        return view('chat', [
            'route' => 'parent',
            'chats' => $chats,
            'chatId' => $chatId,
            'history' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
        ]);
    }
    public function createChat($user,$chatId,$to_user_id){
        $chat = new Chat();
        $chat->id = $chatId;
        $chat->user_id = $user->id;
        $chat->to_user_id = $to_user_id;
        $chat->new_message = true;
        $chat->message = null;
        $chat->save();
        return $chat->refresh();
    }

    public function token(Request $request)
    {
        $appID = config('environment.AGORA_APP_ID');
        $appCertificate = config('environment.AGORA_APP_CERTIFICATE');
        $channelName = $request->channelName;
        $user = Auth::user()->first_name;
        $role = RtcTokenBuilder::RoleAttendee;
        $expireTimeInSeconds = 600;
        $currentTimestamp = now()->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;


        $token = RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, Auth::user()->id, $role, $expireTimeInSeconds);

        return $token;
    }

    public function callUser(Request $request)
    {
        $userToCallArray = [];
        if (is_array($request->user_to_call)){
            foreach ($request->user_to_call as $userToCall) {
                array_push($userToCallArray,$userToCall);
            }
        }
        $data['userToCall'] = $request->user_to_call;
        $data['channelName'] = $request->channel_name;
        $data['from'] = Auth::id();

            foreach ($userToCallArray as $userToCall){
                $callDetails = new CallDetails();
                $callDetails->id = Utility::getUUID();
                $callDetails->host_user_id = $data['from'];
                $callDetails->end_user_id = $userToCall;
                $callDetails->channel_name = $data['channelName'];
                $callDetails->save();
                $data['userToCall'] = $userToCall;
                broadcast(new MakeAgoraCall($data))->toOthers();
        }

        $isRoom = UserCallRoom::where('user_id',$data['from'])->where('chanel_name',$data['channelName'])->first();
        if(!$isRoom){
            $isRoom = new UserCallRoom();
            $isRoom->id = Utility::getUUID();
            $isRoom->user_id = $data['from'];
            $isRoom->chanel_name = $data['channelName'];
            $isRoom->status = 'JOINED';
        }else{
            $isRoom->status = 'JOINED';
            $isRoom->updated_at = Carbon::now();
        }
        $isRoom->save();

        return response()->json([
                'error' => false,
                'status' => 200,
                'message' => "success",
                'data' => [
                    'exp' => 6000
                ],
            ]);
    }

    public function createMeeting(Request $request)
    {
        Log::info($request);
        if (Auth::user()->type != 'Principal') {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }

        $user = Auth::user();
        $meeting = array();
        $meetingT = array();
        $meetingP = array();
        $meeting_hash = Utility::getUUID();

        $validateUser = Validator::make($request->all(),
            [
                'title' => 'required',
                'description' => 'required',
                'meeting_date' => 'required|after:yesterday',
                'meeting_time' => 'required|before:meeting_end_time',
                'meeting_end_time' => 'required|after:meeting_time',
                'teacher_id' => 'required',
                'class_id' => 'required',
                'student_ids' => 'required',
            ]);
        $messages = [
            'meeting_time.required' => 'The meeting start time is required.',
            'meeting_time.before' => 'The meeting start time should be before end time',
            'meeting_end_time.required' => 'The meeting end time is required.',
            'meeting_end_time.after' => 'The meeting end time should be after start time',
            'teacher_id' => 'The teacher field is required',
            'class_id' => 'The classes field is required',
            'student_ids' => 'The students field is required',

        ];
        $validateUser->setCustomMessages($messages);
        if ($validateUser->fails()) {
            $errorMessage = $validateUser->errors()->first();
            return response()->json([
                'error' => true,
                'status' => 400,
                'message' => $errorMessage,
            ]);
        }

        $stuIds = $request->student_ids;
        if (sizeof($stuIds) == 1) {
            if ($stuIds[0] == 'ALL') {
                $stuIds = User::where('type', 'student')
                    ->whereHas('student', function ($query) use ($request) {
                        $query->whereHas('class_section', function ($query) use ($request) {
                            $query->where('class_teacher_id', (int)$request->teacher_id);
                            $query->where('class_id', $request->class_id);
                        });
                    })->pluck('id')->toArray();
            }
        }

        try {

            DB::beginTransaction();

            $mainMeeting = new MainMeeting();
            $mainMeeting->id = Utility::getUUID();
            $mainMeeting->user_id = $user->id;
            $mainMeeting->title = $request->title;
            $mainMeeting->description = $request->description;
            $mainMeeting->meeting_date = $request->meeting_date;
            $mainMeeting->meeting_time = date("H:i:s", strtotime($request->meeting_time));
            $mainMeeting->meeting_end_time = date("H:i:s", strtotime($request->meeting_end_time));
            $mainMeeting->meeting_hash = $meeting_hash;
            $mainMeeting->save();


            $count = 0;
            foreach ($stuIds as $studentIds) {

                $count = $count+1;
                $student = Students::where('user_id', $studentIds)->first();
                if ($student && $student->father_id) {
                    $parent_id = $student->father_id;
                }
                if ($student && $student->mother_id) {
                    $parent_id = $student->mother_id;
                }
                $isParent = User::where('id', $parent_id)->where('type', "parent")->first();
                if (!$isParent) {
                    return response()->json([
                        'error' => true,
                        'status' => 200,
                        'message' => "Invalid student parent detected.",
                    ], 200);
                }
                if(sizeof($stuIds) == 1){
                    $meetingT [] = [
                        'title' => $request->title,
                        'user_id' => $user->id,
                        'principal_id' => $user->id,
                        'student_id' => $studentIds,
                        'parent_id' =>  null,
                        'teacher_id' => $request->teacher_id,
                        'meeting_hash' => $meeting_hash,
                        'meeting_created_user_type' => 'Principal',
                        'meeting_date' => $request->meeting_date,
                        'meeting_time' => date("H:i:s", strtotime($request->meeting_time)),
                        'meeting_end_time' => date("H:i:s", strtotime($request->meeting_end_time)),
                        'description' => $request->description,
                        'status' => 'new',
                    ];
                    $notificationTIds = array_column($meetingT, 'teacher_id');

                    $meetingP [] = [
                        'title' => $request->title,
                        'user_id' => $user->id,
                        'principal_id' => $user->id,
                        'student_id' => $studentIds,
                        'parent_id' =>  $parent_id,
                        'teacher_id' => null,
                        'meeting_hash' => $meeting_hash,
                        'meeting_created_user_type' => 'Principal',
                        'meeting_date' => $request->meeting_date,
                        'meeting_time' => date("H:i:s", strtotime($request->meeting_time)),
                        'meeting_end_time' => date("H:i:s", strtotime($request->meeting_end_time)),
                        'description' => $request->description,
                        'status' => 'new',
                    ];
                    $notificationPIds = array_column($meetingP, 'parent_id');

                }else {

                    if($count == 1){
                        $meeting [] = [
                            'title' => $request->title,
                            'user_id' => $user->id,
                            'principal_id' => $user->id,
                            'student_id' => $studentIds,
                            'parent_id' => null,
                            'teacher_id' =>  (int)$request->teacher_id,
                            'meeting_hash' => $meeting_hash,
                            'meeting_created_user_type' => 'Principal',
                            'meeting_date' => $request->meeting_date,
                            'meeting_time' => date("H:i:s", strtotime($request->meeting_time)),
                            'meeting_end_time' => date("H:i:s", strtotime($request->meeting_end_time)),
                            'description' => $request->description,
                            'status' => 'new',
                        ];
                    }
                    $meeting [] = [
                        'title' => $request->title,
                        'user_id' => $user->id,
                        'principal_id' => $user->id,
                        'student_id' => $studentIds,
                        'parent_id' => $parent_id,
                        'teacher_id' => null,
                        'meeting_hash' => $meeting_hash,
                        'meeting_created_user_type' => 'Principal',
                        'meeting_date' => $request->meeting_date,
                        'meeting_time' => date("H:i:s", strtotime($request->meeting_time)),
                        'meeting_end_time' => date("H:i:s", strtotime($request->meeting_end_time)),
                        'description' => $request->description,
                        'status' => 'new',
                    ];
                }
            }
            if(sizeof($stuIds) > 1){
                $notificationTIds = array_column($meeting, 'teacher_id');
                $notificationPIds = array_column($meeting, 'parent_id');
            }


            Meetings::insert($meeting);
            Meetings::insert($meetingP);
            Meetings::insert($meetingT);
            DB::commit();
            if (sizeof($notificationTIds) > 0) {
                $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                $this->notificationSender->sendNotification(
                    "New meeting scheduled",
                    $message,
                    $notificationTIds, "MEETING", $meeting[0] ?? $meetingP[0]);
                $notificationLogArray = array();
                foreach ($notificationTIds as $notificationTId) {
                    $notificationLogArray [] = [
                        "id" => Utility::getUUID(),
                        "user_id" => $notificationTId,
                        "from_user_id" => $user->id,
                        "message" => $message,
                        "type" => "MEETING",
                        "title" => "New meeting scheduled",
                        'created_at' => Carbon::now()
                    ];
                }
                $this->notificationSender->insertLogs($notificationLogArray);

            }
            if (sizeof($notificationPIds) > 0) {
                $message = "A new meeting is scheduled for " . $request->meeting_date . " " . $request->meeting_time;
                $this->notificationSender->sendNotification(
                    "New meeting scheduled",
                    $message,
                    $notificationPIds, "MEETING", $meeting[0] ?? $meetingP[0]);
                $notificationLogArray = array();
                foreach ($notificationPIds as $notificationPId) {
                    $notificationLogArray [] = [
                        "id" => Utility::getUUID(),
                        "user_id" => $notificationPId,
                        "from_user_id" => $user->id,
                        "message" => $message,
                        "type" => "MEETING",
                        "title" => "New meeting scheduled",
                        'created_at' => Carbon::now()
                    ];
                }
                $this->notificationSender->insertLogs($notificationLogArray);

            }
            return response()->json([
                'error' => false,
                'status' => 200,
                'message' => "Meeting Created",
            ], 200);

        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
        }
        return response()->json([
            'error' => true,
            'status' => 200,
            'message' => "Failed to create meeting",
        ], 200);
    }

    public function meetingListView()
    {
        $user = Auth::user();
        $school = principalSchoolOrFail($user->id);
        $class = ClassSchool::where('school_id', $school->id)->with('sections')->get();

        return view('meeting.index')->with([
            'classes' => $class
        ]);
    }

    public function meetingList(Request $request)
    {
        $user = Auth::user();

        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'DESC';
        $status = null;
        $class_id = $_GET['class_id'] ?? null;
        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;

        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        $meetings = Meetings::select('id', 'title', 'principal_id', 'user_id', 'teacher_id', 'parent_id', 'meeting_date', 'meeting_time', 'meeting_end_time', 'description', 'student_id', 'meeting_hash', 'status')->where(function ($query) use ($user) {
            $query->where('parent_id', $user->id);
            $query->orWhere('teacher_id', $user->id);
            $query->orWhere('principal_id', $user->id);
        })
            ->with(['teacher' => function ($query) {
                $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
            }])
            ->orderBy('id', 'DESC')
            ->groupBy('meeting_hash');

        $total = $meetings->count();

        $meetings->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $meetings->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();

        $meetingsStudents = Meetings::where(function ($query) use ($user) {
            $query->where('parent_id', $user->id);
            $query->orWhere('teacher_id', $user->id);
            $query->orWhere('principal_id', $user->id);

        })->where(function ($query) use ($status) {
            if ($status) {
                $query->where('status', $status);
            }
        })->with('student.student.class_section.class')->groupBy('student_id')->get();
        $meetingsParents = Meetings::where(function ($query) use ($user) {
            $query->where('parent_id', $user->id);
            $query->orWhere('teacher_id', $user->id);
        })->where(function ($query) use ($status) {
            if ($status) {
                $query->where('status', $status);
            }
        })->with(['parent' => function ($query) {
            $query->select('id', 'title', 'first_name', 'last_name', 'gender', 'image', 'mobile');
        }])->groupBy('student_id')->get();

        foreach ($res as $meeting) {
            $parents = [];

            foreach ($meetingsParents as $meetingsParent) {
                $students = [];
                if ($meeting->meeting_hash == $meetingsParent->meeting_hash && $meetingsParent->parent->id == $meeting->parent_id) {

                    foreach ($meetingsStudents as $meetingsStudent) {
                        if ($meetingsStudent->student) {
                            if ($meetingsParent->meeting_hash == $meetingsStudent->meeting_hash
                                && ($meetingsStudent->student->student->father_id == $meetingsParent->parent->id || $meetingsStudent->student->student->mother_id == $meetingsParent->parent->id)) {
                                $students[] = $meetingsStudent->student;
                            }
                        }
                    }
                    $meetingsParent->parent->students = $students;
                }
                $parents[] = $meetingsParent->parent;
            }
            $meeting->_parents = $parents;
        }

        foreach ($res as $row) {
            $operate = '<a class="btn btn-xs btn-gradient-primary btn-rounded btn-icon editdata"  data-id=' . $row->id . '  data-hash=' . $row->meeting_hash . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';

            $tempRow['id'] = $row->id;
            $tempRow['title'] = $row->title;
            $tempRow['description'] = $row->description;
            $tempRow['meeting_hash'] = $row->meeting_hash;
            $tempRow['date'] = $row->meeting_date;
            $tempRow['is_principal'] = $row->principal_id ? "Required" : "Optional";
            $tempRow['meeting_time'] = $row->meeting_time;
            $tempRow['meeting_end_time'] = $row->meeting_end_time;
            $tempRow['status'] = $row->status;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function updateMeeting(Request $request)
    {
        $status = (boolean)json_decode(strtolower($request->status));
        $meeting_hash = $request->meeting_hash;
        $id = $request->id;
        $status = $status ? 'accepted' : 'rejected';
        Meetings::where('meeting_hash', $meeting_hash)->update(
            [
                'status' => $status
            ]
        );
        $response = [
            'error' => false,
            'message' => "Meeting Updated",
        ];
        return response()->json($response);
    }


    public function meetingDetails($meetingHash)
    {
        $meetingDetails=Meetings::where('meeting_hash','=',$meetingHash)
            ->with('parent')
            ->with('student')
            ->with('teacher')
            ->get();
        $user=[];

        foreach ($meetingDetails as $meetingDetail) {
            if (!in_array($meetingDetail->parent, $user)) {
                array_push($user, $meetingDetail->parent);
            }

            if (!in_array($meetingDetail->teacher, $user)) {
                array_push($user, $meetingDetail->teacher);
            }
        }
        return view('eventDetails')->with([
            'meetingDetails' => $meetingDetails,
            'user'=>$user
        ]);
    }

    public function makeCallRoom(Request $request){
        $channel_name = $request->channel_name;
        $user_id = Auth::user()->id;

        $isActive = UserSubscription::where('user_id',$user_id)
            ->where('status',"ACTIVE")->where('remaining_time','!=','0')->first();

        if(!$isActive){
            $response = [
                'error' => true,
                'message' => "No Active Subscription",
                'data' => UserSubscription::where('user_id',$user_id)
                    ->where('status',"ACTIVE")->first()
            ];
            return response()->json($response,400);
        }


        $isRoom = UserCallRoom::where('user_id',$user_id)->where('chanel_name',$channel_name)->first();
        if(!$isRoom){
            $isRoom = new UserCallRoom();
            $isRoom->id = Utility::getUUID();
            $isRoom->user_id = $user_id;
            $isRoom->chanel_name = $channel_name;
            $isRoom->status = 'JOINED';
        }else{
            $isRoom->status = 'JOINED';
            $isRoom->updated_at = Carbon::now();
        }
        $isRoom->save();
        $response = [
            'error' => false,
            'message' => "Room Updated",
            'data' => $isRoom,
        ];
        return response()->json($response);
    }

    public function leftFormCallRoom(Request $request){
        $channel_name = $request->channel_name;
        $user_id = Auth::user()->id;

        $isRoom = UserCallRoom::where('user_id',$user_id)
            ->where('chanel_name',$channel_name)
            ->where('status','JOINED')
            ->first();
        if($isRoom){
            $isRoom->status = 'LEFT';
            $isRoom->updated_at = Carbon::now();
            $isRoom->save();
        }

        $response = [
            'error' => false,
            'message' => "Room Updated",
            'data' => $isRoom,
        ];
        return response()->json($response);

    }

    public function channelToken(Request $request){
        $isActive = UserSubscription::where('user_id',Auth::user()->id)
            ->where('status','ACTIVE')->where('remaining_time','!=','0')->first();
        if(!$isActive){
            $response = [
                'error' => true,
                'message' => "No Active Subscription",
                'data' => $isActive,
            ];
            return response()->json($response,400);
        }

        $channelName = $request->channel_name;
        $appID = config('environment.AGORA_APP_ID');
        $appCertificate = config('environment.AGORA_APP_CERTIFICATE');
        $role = RtcTokenBuilder::RoleAttendee;
        $expireTimeInSeconds = 660;

        $token = RtcTokenBuilder::buildTokenWithUid(
            $appID,
            $appCertificate,
            $channelName,
            Auth::user()->id,
            $role,
            $expireTimeInSeconds
        );
        $response = [
            'error' => false,
            'message' => "Channel token",
            'data' => [
                "token" => $token
            ],
        ];
        return response()->json($response);
    }

}
