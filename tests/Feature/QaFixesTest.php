<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\NotificationSender;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * QA run 2026-11-08: chat over MySQL + Reverb (Part A) and the backend items of Part B.
 */
class QaFixesTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $schoolId;
    private User $teacher;
    private User $parent;
    private User $guardian;
    private User $kid;
    private int $classId;
    private int $sectionId;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['Teacher', 'Parent', 'Student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        // No real push notifications from tests.
        $this->mock(NotificationSender::class)->shouldIgnoreMissing();
        Storage::fake('public');

        $this->countryId = DB::table('countries')->insertGetId(['code' => 'GB', 'name' => 'United Kingdom', 'phonecode' => 44]);
        $this->teacher = $this->makeUser('teacher', 'Tara');
        $this->parent = $this->makeUser('parent', 'Paula');
        $this->guardian = $this->makeUser('parent', 'Gwen');
        $this->schoolId = DB::table('schools')->insertGetId([
            'user_id' => $this->teacher->id, 'country_id' => $this->countryId, 'school_name' => 'QA Academy',
            'address' => 'Road', 'image' => '', 'post_code' => '00000',
        ]);
        foreach ([$this->teacher, $this->parent, $this->guardian] as $user) {
            DB::table('user_schools')->insert(['user_id' => $user->id, 'school_id' => $this->schoolId]);
        }
        $this->classId = DB::table('classes')->insertGetId(['name' => 'QA Class 6A', 'school_id' => $this->schoolId, 'grade' => '6']);
        $section = DB::table('sections')->insertGetId(['name' => 'A', 'school_id' => $this->schoolId]);
        $this->sectionId = DB::table('class_sections')->insertGetId([
            'class_id' => $this->classId, 'section_id' => $section, 'class_teacher_id' => $this->teacher->id,
        ]);
        // Guardian-only pupil, like Gina QAKid on live.
        $this->kid = $this->makeUser('student', 'Gina');
        DB::table('parents')->insert(['user_id' => $this->guardian->id, 'first_name' => 'Gwen', 'last_name' => 'Guardian', 'gender' => 'female', 'email' => $this->guardian->email]);
        DB::table('students')->insert([
            'user_id' => $this->kid->id, 'class_section_id' => $this->sectionId, 'guardian_id' => $this->guardian->id,
            'relationship' => 'Legal Guardian', 'class_status' => 'ACCEPTED',
        ]);
    }

    private function makeUser(string $type, string $first): User
    {
        $user = User::forceCreate([
            'first_name' => $first, 'last_name' => ucfirst($type), 'email' => $type . uniqid() . '@example.com',
            'password' => Hash::make('secret123'), 'type' => $type, 'country_id' => $this->countryId,
            'email_verified_at' => now(), 'status' => 1,
        ]);
        $user->assignRole(ucfirst($type));
        return $user;
    }

    private function send(User $from, User $to, array $data = [])
    {
        $this->actingAs($from, 'sanctum');
        return $this->post('/api/chat/send', array_merge(['to_user_id' => $to->id], $data), ['Accept' => 'application/json']);
    }

    // ---------------------------------------------------------------- Part A: chat

    public function test_send_text_stores_history_updates_summary_and_broadcasts(): void
    {
        Event::fake([MessageSent::class]);

        $response = $this->send($this->teacher, $this->parent, ['message' => 'Hello Paula'])->assertOk();

        $response->assertJsonStructure(['message', 'sender_id', 'file', 'file_extension', 'is_image', 'sender_name', 'receiver_name', 'created_at',
            'chat_id', 'chat_message' => ['id', 'chat_id', 'sender_id', 'receiver_id', 'message', 'file_url', 'file_extension', 'is_image', 'created_at', 'read_at']]);
        $chatId = $response->json('chat_id');
        $response->assertJsonPath('chat_message.message', 'Hello Paula')
            ->assertJsonPath('chat_message.file_url', null)
            ->assertJsonPath('chat_message.receiver_id', $this->parent->id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}Z$/', $response->json('chat_message.created_at'));
        $this->assertSame(1, ChatMessage::where('chat_id', $chatId)->count());
        $summary = DB::table('chat')->where('id', $chatId)->first();
        $this->assertSame('Hello Paula', json_decode($summary->message, true)['message']);
        $this->assertEquals(1, $summary->new_message);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($chatId) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            return $channels === ["private-chat.user.{$this->teacher->id}", "private-chat.user.{$this->parent->id}"]
                && $event->broadcastAs() === 'message.sent'
                && $event->broadcastWith()['chat_id'] === $chatId;
        });

        // A reply reuses the same chat row.
        $this->send($this->parent, $this->teacher, ['message' => 'Hi'])->assertOk()->assertJsonPath('chat_id', $chatId);
        $this->assertSame(1, DB::table('chat')->count());
    }

    public function test_files_are_stored_per_chat_with_absolute_urls_and_validated(): void
    {
        $image = $this->send($this->teacher, $this->parent, ['file' => UploadedFile::fake()->image('photo.png'), 'file_extension' => 'png'])->assertOk();
        $chatId = $image->json('chat_id');
        $this->assertTrue($image->json('chat_message.is_image'));
        $this->assertStringStartsWith('http', $image->json('chat_message.file_url'));
        $this->assertStringContainsString("/storage/chat/{$chatId}/", $image->json('chat_message.file_url'));
        $this->assertSame($image->json('chat_message.file_url'), $image->json('file'));
        Storage::disk('public')->assertExists(ChatMessage::first()->file_path);

        $pdf = $this->send($this->teacher, $this->parent, ['file' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'), 'file_extension' => 'pdf'])->assertOk();
        $this->assertFalse($pdf->json('chat_message.is_image'));

        $this->send($this->teacher, $this->parent, ['file' => UploadedFile::fake()->create('x.exe', 1), 'file_extension' => 'exe'])
            ->assertStatus(422)->assertJsonStructure(['message']);
        $this->send($this->teacher, $this->parent, ['file' => UploadedFile::fake()->create('big.pdf', 10241), 'file_extension' => 'pdf'])
            ->assertStatus(422)->assertJsonPath('message', 'The file may not be larger than 10 MB.');
        $this->send($this->teacher, $this->parent, [])->assertStatus(422)->assertJsonPath('message', 'A message or a file is required.');
    }

    public function test_history_pages_with_before_id_and_only_participants_can_read(): void
    {
        foreach (range(1, 5) as $i) {
            $chatId = $this->send($this->teacher, $this->parent, ['message' => "m$i"])->json('chat_id');
        }
        $this->actingAs($this->parent, 'sanctum');

        $first = $this->getJson("/api/chat/$chatId/messages?limit=2")->assertOk();
        $this->assertSame(['m4', 'm5'], array_column($first->json('data'), 'message'));
        $first->assertJsonPath('has_more', true);

        $second = $this->getJson("/api/chat/$chatId/messages?limit=2&before_id=" . $first->json('data.0.id'))->assertOk();
        $this->assertSame(['m2', 'm3'], array_column($second->json('data'), 'message'));
        $last = $this->getJson("/api/chat/$chatId/messages?limit=2&before_id=" . $second->json('data.0.id'))->assertOk();
        $this->assertSame(['m1'], array_column($last->json('data'), 'message'));
        $last->assertJsonPath('has_more', false);
        $this->assertCount(5, $this->getJson("/api/chat/$chatId/messages?limit=500")->json('data'));

        $this->actingAs($this->guardian, 'sanctum');
        $this->getJson("/api/chat/$chatId/messages")->assertForbidden();
        $this->getJson('/api/chat/00000000-0000-0000-0000-000000000000/messages')->assertNotFound();
    }

    public function test_read_marks_only_the_callers_incoming_messages(): void
    {
        $chatId = $this->send($this->teacher, $this->parent, ['message' => 'to parent'])->json('chat_id');
        $this->send($this->parent, $this->teacher, ['message' => 'to teacher']);

        $this->actingAs($this->parent, 'sanctum');
        $this->postJson('/api/chat/read', ['id' => $chatId])->assertOk();

        $this->assertNotNull(ChatMessage::where('message', 'to parent')->value('read_at'));
        $this->assertNull(ChatMessage::where('message', 'to teacher')->value('read_at'));
    }

    public function test_send_still_succeeds_when_reverb_is_down(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'ptm-live-key', 'secret' => 'secret', 'app_id' => 'ptm',
            'options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => ['timeout' => 1, 'connect_timeout' => 1],
        ]]);
        app(BroadcastManager::class)->purge();

        $this->send($this->teacher, $this->parent, ['message' => 'offline realtime'])->assertOk()->assertJsonPath('chat_message.message', 'offline realtime');
        $this->assertSame(1, ChatMessage::count());
    }

    public function test_channel_auth_signs_only_the_users_own_channel(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'ptm-live-key', 'secret' => 'secret', 'app_id' => 'ptm',
            'options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
        ]]);
        app(BroadcastManager::class)->purge();
        require base_path('routes/channels.php');

        $this->actingAs($this->parent, 'sanctum');
        $auth = $this->postJson('/api/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-chat.user.{$this->parent->id}"])
            ->assertOk()->json('auth');
        $this->assertSame('ptm-live-key:' . hash_hmac('sha256', "123.456:private-chat.user.{$this->parent->id}", 'secret'), $auth);

        $this->postJson('/api/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-chat.user.{$this->teacher->id}"])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- Part B

    public function test_guardian_only_pupil_has_a_primary_parent_in_teacher_lists(): void
    {
        $this->actingAs($this->teacher, 'sanctum');
        $kid = collect($this->getJson('/api/teacher/class/students?class_id=' . $this->classId)->assertOk()->json('data'))->firstWhere('id', $this->kid->id);
        $this->assertSame(['id' => $this->guardian->id, 'first_name' => 'Gwen', 'last_name' => 'Guardian', 'relationship' => 'Legal Guardian',
            'email' => $this->guardian->email, 'mobile' => null], $kid['primary_parent']);
        $this->assertSame('Gwen', $kid['student']['guardian']['first_name']);

        $parents = $this->getJson('/api/teacher/student/parents?student_id=' . $this->kid->id)->assertOk()->json('data');
        $this->assertSame('Legal Guardian', $parents[0]['relationship']);

        // Pending application: the teacher sees who applied.
        DB::table('students')->where('user_id', $this->kid->id)->update(['class_status' => 'PENDING']);
        $request = collect($this->getJson('/api/teacher/class/students/requests?class_id=' . $this->classId)->assertOk()->json('data'))->firstWhere('id', $this->kid->id);
        $this->assertSame('Legal Guardian', $request['relationship']);
        $this->assertSame($this->guardian->id, $request['primary_parent']['id']);
    }

    public function test_validation_failures_return_422_not_401(): void
    {
        $this->actingAs($this->teacher, 'sanctum');
        $this->postJson('/api/add/meetings-v2', ['title' => 'No date'])
            ->assertStatus(422)->assertJsonPath('status', 400)->assertJsonStructure(['message']);
    }

    public function test_meetings_cannot_start_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00', 'UTC')); // 14:00 in Karachi
        $this->actingAs($this->teacher, 'sanctum');
        $body = ['title' => 'Catch-up', 'meeting_date' => '2026-11-08', 'meeting_time' => "1:00\u{202F}PM", 'meeting_end_time' => "1:30\u{202F}PM",
            'student_parent_ids' => [['parent_id' => $this->parent->id, 'student_id' => null]]];

        $this->postJson('/api/add/meetings-v2', $body, ['X-Timezone' => 'Asia/Karachi'])
            ->assertStatus(422)->assertJsonPath('message', 'Meeting start time must be in the future');
        // Within the 5 minute grace it is accepted.
        $this->postJson('/api/add/meetings-v2', array_merge($body, ['meeting_time' => '13:56', 'meeting_end_time' => '14:30']), ['X-Timezone' => 'Asia/Karachi'])
            ->assertJsonMissing(['message' => 'Meeting start time must be in the future']);
        // The same wall-clock time is still ahead in London.
        $this->postJson('/api/add/meetings-v2', $body, ['X-Timezone' => 'Europe/London'])
            ->assertJsonMissing(['message' => 'Meeting start time must be in the future']);
        Carbon::setTestNow();
    }

    public function test_attendance_with_subject_null_is_not_filtered_by_subject(): void
    {
        $sessionYear = DB::table('session_years')->insertGetId(['name' => '2026-27', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $maths = DB::table('subjects')->insertGetId(['name' => 'Maths', 'image' => '']);
        foreach ([null, $maths] as $subject) {
            DB::table('attendances')->insert(['class_section_id' => $this->sectionId, 'student_id' => $this->kid->id, 'session_year_id' => $sessionYear,
                'type' => 1, 'date' => '2026-11-08', 'remark' => '', 'subject_id' => $subject]);
        }
        $this->actingAs($this->teacher, 'sanctum');
        $url = '/api/teacher/view-attendance/class?class_id=' . $this->classId . '&date=2026-11-08';

        $this->getJson($url . '&subject_id=null')->assertOk()->assertJsonPath('data.present_count', 2);
        $this->getJson($url . '&subject_id=' . $maths)->assertOk()->assertJsonPath('data.present_count', 1);
    }

    public function test_teacher_can_update_their_class(): void
    {
        $this->actingAs($this->teacher, 'sanctum');
        $this->postJson('/api/teacher/class/update', ['class_id' => $this->classId, 'name' => 'QA Class 6B', 'grade' => '6'])
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.name', 'QA Class 6B')
            ->assertJsonStructure(['data' => ['id', 'name', 'grade', 'school_id', 'all_subjects', 'class_section']]);

        DB::table('classes')->insert(['name' => 'Taken', 'school_id' => $this->schoolId, 'grade' => '6']);
        $this->postJson('/api/teacher/class/update', ['class_id' => $this->classId, 'name' => 'Taken'])->assertStatus(400);
        $this->postJson('/api/teacher/class/update', [])->assertStatus(422);

        $this->actingAs($this->makeUser('teacher', 'Other'), 'sanctum');
        $this->postJson('/api/teacher/class/update', ['class_id' => $this->classId, 'name' => 'Hijack'])->assertForbidden();
    }

    public function test_legal_pages_are_public(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms &amp; Conditions', false)->assertSee('Draft placeholder');
        $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy')->assertSee('Draft placeholder');

        DB::table('settings')->insert(['type' => 'privacy_policy', 'message' => '<p>Final privacy text</p>']);
        $this->get('/privacy-policy')->assertOk()->assertSee('Final privacy text', false)->assertDontSee('Draft placeholder');
    }

    public function test_invalid_token_gets_json_401(): void
    {
        $this->getJson('/api/auth/details', ['Authorization' => 'Bearer 1|not-a-real-token'])
            ->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_school_names_must_be_real_words(): void
    {
        $this->actingAs($this->teacher, 'sanctum');
        foreach (['mz', '1234', '!!!'] as $name) {
            $this->postJson('/api/add/school', ['country_id' => $this->countryId, 'school_name' => $name, 'post_code' => 'AB1 2CD'])
                ->assertStatus(422);
        }
    }
}
