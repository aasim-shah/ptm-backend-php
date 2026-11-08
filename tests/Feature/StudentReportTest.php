<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Report cards: saving marks per student and subject, publishing to parents, deleting, and the parent view.
 */
class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    private $notifications;
    private int $countryId;
    private User $teacher;
    private User $parent;
    private int $classId;
    private int $sectionId;
    private array $subjects = [];
    private int $sessionYearId;
    private string $quarterId;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['Teacher', 'Parent', 'Student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        // No real push notifications from tests.
        $this->notifications = $this->spy(NotificationSender::class);
        $this->countryId = DB::table('countries')->insertGetId(['code' => 'US', 'name' => 'United States', 'phonecode' => 1]);

        $this->teacher = $this->makeUser('teacher');
        $this->parent = $this->makeUser('parent');
        $school = DB::table('schools')->insertGetId([
            'user_id' => $this->teacher->id, 'country_id' => $this->countryId, 'school_name' => 'Report School',
            'address' => 'Road', 'image' => '', 'post_code' => '00000',
        ]);
        $this->classId = DB::table('classes')->insertGetId(['name' => 'Class 5A', 'school_id' => $school]);
        $section = DB::table('sections')->insertGetId(['name' => 'A', 'school_id' => $school]);
        $this->sectionId = DB::table('class_sections')->insertGetId([
            'class_id' => $this->classId, 'section_id' => $section, 'class_teacher_id' => $this->teacher->id,
        ]);
        foreach (['Maths', 'English', 'Science'] as $name) {
            $subject = DB::table('subjects')->insertGetId(['name' => $name, 'image' => '', 'teacher_id' => $this->teacher->id]);
            DB::table('class_subjects')->insert(['class_id' => $this->classId, 'subject_id' => $subject, 'type' => 'Compulsory']);
            $this->subjects[] = $subject;
        }
        $this->sessionYearId = DB::table('session_years')->insertGetId([
            'name' => '2026-27', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
        ]);
        $this->quarterId = $this->makeQuarter('Quarter 1');
    }

    // ---------------------------------------------------------------- helpers

    private function makeUser(string $type): User
    {
        $user = User::forceCreate([
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'email' => $type . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'type' => $type,
            'country_id' => $this->countryId,
            'email_verified_at' => now(),
            'status' => 1,
        ]);
        $user->assignRole(ucfirst($type));
        return $user;
    }

    private function makeStudent(): User
    {
        $student = $this->makeUser('student');
        DB::table('students')->insert([
            'user_id' => $student->id, 'class_section_id' => $this->sectionId, 'mother_id' => $this->parent->id,
        ]);
        return $student;
    }

    private function makeQuarter(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('quarters')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function row(User $student, int $subjectId, $points = 80, $missingCause = null): array
    {
        return ['student_id' => $student->id, 'subject_id' => $subjectId, 'points' => $points,
            'grade' => $points === null ? null : 'B', 'review' => null, 'missing_cause' => $missingCause];
    }

    private function save(User $as, array $reports, string $endpoint = 'create', ?string $quarterId = null)
    {
        $this->actingAs($as, 'sanctum');
        return $this->postJson('/api/teacher/' . $endpoint . '/student-report', [
            'quarter_id' => $quarterId ?? $this->quarterId,
            'class_id' => $this->classId,
            'subject_id' => $reports[0]['subject_id'] ?? null,
            'session_year_id' => $this->sessionYearId,
            'reports' => $reports,
        ]);
    }

    private function classQuarter(?string $quarterId = null)
    {
        return DB::table('class_quarter')->where('class_id', $this->classId)
            ->where('quarter_id', $quarterId ?? $this->quarterId)->first();
    }

    private function publishedCount(?User $student = null): int
    {
        return DB::table('student_quarter')->where('quarter_id', $this->quarterId)
            ->when($student, fn ($q) => $q->where('student_id', $student->id))->count();
    }

    private function allSubjects(User $student): array
    {
        return array_map(fn ($subject) => $this->row($student, $subject), $this->subjects);
    }

    // ---------------------------------------------------------------- tests

    public function test_all_subjects_for_one_student_in_one_request_publishes_the_report(): void
    {
        $student = $this->makeStudent();

        $this->save($this->teacher, $this->allSubjects($student))
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('report', true);

        $this->assertSame(3, DB::table('student_report_cards')->where('student_id', $student->id)->count());
        $this->assertSame('PUBLISHED', $this->classQuarter()->status);
        $this->assertSame(1, $this->publishedCount($student));
        $this->notifications->shouldHaveReceived('sendNotification')
            ->withArgs(fn ($title, $message, $ids) => $title === 'Report Published' && $ids === [$this->parent->id])->once();

        $this->actingAs($this->parent, 'sanctum');
        $this->getJson('/api/parent/student/report?student_id=' . $student->id . '&quarter_id=' . $this->quarterId
            . '&session_year_id=' . $this->sessionYearId)
            ->assertOk()->assertJsonPath('error', false)
            ->assertJsonCount(3, 'data.published.quarter.student_report_cards')
            ->assertJsonPath('data.reports', []);
    }

    public function test_subjects_must_be_assigned_to_the_class(): void
    {
        $student = $this->makeStudent();
        $other = DB::table('subjects')->insertGetId(['name' => 'Latin', 'image' => '']);

        $this->save($this->teacher, [$this->row($student, $this->subjects[0]), $this->row($student, $other)])
            ->assertOk()->assertJsonPath('error', true);
        $this->assertSame(0, DB::table('student_report_cards')->count());
    }

    public function test_students_must_belong_to_the_class(): void
    {
        $outsider = $this->makeUser('student');

        $this->save($this->teacher, [$this->row($outsider, $this->subjects[0])])
            ->assertOk()->assertJsonPath('error', true)->assertJsonPath('message', 'Some of provided students are invalid.');
    }

    public function test_validation_and_missing_session_year_return_clear_errors(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->teacher, 'sanctum');
        $this->postJson('/api/teacher/create/student-report', ['class_id' => $this->classId])
            ->assertOk()->assertJsonPath('error', true);

        $this->postJson('/api/teacher/create/student-report', [
            'quarter_id' => $this->quarterId, 'class_id' => $this->classId, 'session_year_id' => 999999,
            'reports' => [$this->row($student, $this->subjects[0])],
        ])->assertOk()->assertJsonPath('error', true)->assertJsonPath('message', 'Invalid session year detected.');
    }

    public function test_a_subject_teacher_can_save_marks_for_their_own_subject_only(): void
    {
        $student = $this->makeStudent();
        $subjectTeacher = $this->makeUser('teacher');
        DB::table('subject_teachers')->insert([
            'class_section_id' => $this->sectionId, 'subject_id' => $this->subjects[1], 'teacher_id' => $subjectTeacher->id,
        ]);

        $this->save($subjectTeacher, [$this->row($student, $this->subjects[1], 72)])
            ->assertOk()->assertJsonPath('error', false);
        $this->assertSame(1, DB::table('student_report_cards')->where('subject_id', $this->subjects[1])->count());

        $this->save($subjectTeacher, [$this->row($student, $this->subjects[0], 50)])
            ->assertOk()->assertJsonPath('error', true)
            ->assertJsonPath('message', 'You can only save marks for the subjects you teach in this class.');

        $this->save($this->makeUser('teacher'), [$this->row($student, $this->subjects[0])])->assertForbidden();
    }

    public function test_only_complete_students_are_published_and_blank_rows_are_not_stored(): void
    {
        $complete = $this->makeStudent();
        $partial = $this->makeStudent();

        $reports = $this->allSubjects($complete);
        $reports[] = $this->row($partial, $this->subjects[0]);
        $reports[] = $this->row($partial, $this->subjects[1], null);   // blank: not stored
        $reports[] = $this->row($complete, $this->subjects[2], null, 'Absent'); // missing cause counts
        $this->save($this->teacher, $reports)->assertOk()->assertJsonPath('error', false);

        $this->assertSame(1, DB::table('student_report_cards')->where('student_id', $partial->id)->count());
        $this->assertSame(1, $this->publishedCount($complete));
        $this->assertSame(0, $this->publishedCount($partial));
        $this->assertSame('PENDING', $this->classQuarter()->status);

        // Completing the second student publishes them and the class.
        $this->save($this->teacher, [$this->row($partial, $this->subjects[1]), $this->row($partial, $this->subjects[2])], 'update')
            ->assertOk()->assertJsonPath('error', false);
        $this->assertSame(1, $this->publishedCount($partial));
        $this->assertSame('PUBLISHED', $this->classQuarter()->status);
    }

    public function test_manual_publish_publishes_partial_reports_once(): void
    {
        $partial = $this->makeStudent();
        $empty = $this->makeStudent();
        $this->save($this->teacher, [$this->row($partial, $this->subjects[0])])->assertJsonPath('error', false);
        $this->assertSame('PENDING', $this->classQuarter()->status);

        $publish = fn () => $this->postJson('/api/teacher/publish/student-report', [
            'class_id' => $this->classId, 'quarter_id' => $this->quarterId, 'session_year_id' => $this->sessionYearId,
        ]);
        $publish()->assertOk()->assertJsonPath('error', false)
            ->assertJsonPath('data.status', 'PUBLISHED')->assertJsonPath('data.published_students', 1);
        $this->assertSame(1, $this->publishedCount($partial));
        $this->assertSame(0, $this->publishedCount($empty));
        $this->assertSame('PUBLISHED', $this->classQuarter()->status);

        $publish()->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.published_students', 0);
        $this->assertSame(1, $this->publishedCount());
    }

    public function test_manual_publish_needs_marks_and_class_ownership(): void
    {
        $this->makeStudent();
        $body = ['class_id' => $this->classId, 'quarter_id' => $this->quarterId, 'session_year_id' => $this->sessionYearId];

        $this->actingAs($this->teacher, 'sanctum');
        $this->postJson('/api/teacher/publish/student-report', $body)->assertOk()->assertJsonPath('error', true);

        $this->actingAs($this->makeUser('teacher'), 'sanctum');
        $this->postJson('/api/teacher/publish/student-report', $body)->assertForbidden();
    }

    public function test_editing_a_published_report_keeps_it_published_and_notifies_the_parent(): void
    {
        $student = $this->makeStudent();
        $this->save($this->teacher, $this->allSubjects($student))->assertJsonPath('error', false);

        $this->save($this->teacher, [$this->row($student, $this->subjects[0], 95)], 'update')
            ->assertOk()->assertJsonPath('error', false);

        $this->assertSame(1, $this->publishedCount($student));
        $this->assertSame('PUBLISHED', $this->classQuarter()->status);
        $this->assertEquals(95, DB::table('student_report_cards')->where('subject_id', $this->subjects[0])->value('points'));
        $this->notifications->shouldHaveReceived('sendNotification')
            ->withArgs(fn ($title, $message, $ids) => $title === 'Report Updated' && $ids === [$this->parent->id])->once();

        // A subject added to the class later does not unpublish anyone.
        $new = DB::table('subjects')->insertGetId(['name' => 'Art', 'image' => '']);
        DB::table('class_subjects')->insert(['class_id' => $this->classId, 'subject_id' => $new, 'type' => 'Compulsory']);
        $this->save($this->teacher, [$this->row($student, $new)], 'update')->assertJsonPath('error', false);
        $this->assertSame(1, $this->publishedCount($student));
        $this->assertSame('PUBLISHED', $this->classQuarter()->status);
    }

    public function test_delete_uses_the_session_year_id(): void
    {
        $student = $this->makeStudent();
        $this->save($this->teacher, $this->allSubjects($student))->assertJsonPath('error', false);
        // The old lookup went by name; renaming the year must not break deleting.
        DB::table('session_years')->where('id', $this->sessionYearId)->update(['name' => 'Renamed year']);

        $this->deleteJson('/api/teacher/delete/student-report/' . $this->classQuarter()->id)
            ->assertOk()->assertJsonPath('error', false);

        $this->assertNull($this->classQuarter());
        $this->assertSame(0, DB::table('student_report_cards')->count());
        $this->assertSame(0, $this->publishedCount());
    }

    public function test_parent_report_without_quarter_returns_the_latest_published_quarter(): void
    {
        $student = $this->makeStudent();
        $this->save($this->teacher, $this->allSubjects($student))->assertJsonPath('error', false);
        $later = $this->makeQuarter('Quarter 2');
        $this->travel(1)->minutes();
        $this->save($this->teacher, [$this->row($student, $this->subjects[0], 60)], 'create', $later);
        $this->postJson('/api/teacher/publish/student-report', [
            'class_id' => $this->classId, 'quarter_id' => $later, 'session_year_id' => $this->sessionYearId,
        ])->assertJsonPath('error', false);

        $this->actingAs($this->parent, 'sanctum');
        $url = '/api/parent/student/report?student_id=' . $student->id . '&session_year_id=' . $this->sessionYearId;

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.published.quarter_id', $later)
            ->assertJsonCount(1, 'data.published.quarter.student_report_cards');

        $this->getJson($url . '&quarter_id=' . $this->quarterId)->assertOk()
            ->assertJsonPath('data.published.quarter_id', $this->quarterId)
            ->assertJsonCount(3, 'data.published.quarter.student_report_cards');

        $this->getJson('/api/parent/student/report?student_id=' . $student->id . '&session_year_id=999999')
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.published', null);
    }
}
