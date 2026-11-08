<?php

namespace App\Http\Controllers\Workspace;

use App\Models\User;
use App\Services\WorkspaceInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk imports: download a template, upload a CSV, review a validation preview,
 * then confirm. People rows connect existing verified accounts or send secure
 * invitations; class rows create class sections with their class teacher.
 */
class ImportsController extends AdminPage
{
    public const MAX_ROWS = 1000;
    public const KINDS = [
        'people' => ['People', ['email', 'first_name', 'last_name', 'role', 'school'], 'Parents, teachers and staff. Role is parent, teacher or staff.'],
        'classes' => ['Classes', ['class', 'section', 'teacher_email', 'school'], 'Class sections and their class teacher, who must already be a teacher at the school.'],
        'students' => ['Students', ['first_name', 'last_name', 'class', 'section', 'school', 'admission_no', 'guardian_email'], 'Students placed in an existing class section. A guardian email links an existing parent.'],
    ];
    /** Columns a file may leave out. */
    private const OPTIONAL = ['school', 'admission_no', 'guardian_email'];

    private function permission(array $context, string $kind): bool
    {
        return $this->access->can($context, in_array($kind, ['classes', 'students'], true) ? 'academics.manage' : 'memberships.manage');
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->permission($context, 'people') || $this->permission($context, 'classes'), 403);
        $current = $request->integer('import') ? DB::table('workspace_imports')->where('id', $request->integer('import'))->where('user_id', $request->user()->id)->where('context', $context['id'])->first() : null;
        if ($current) { $current->rows = json_decode($current->rows, true); $current->summary = json_decode($current->summary ?? '[]', true); }
        $recent = DB::table('workspace_imports')->where('user_id', $request->user()->id)->where('context', $context['id'])->orderByDesc('id')->limit(10)->get();
        $kinds = array_filter(self::KINDS, fn ($k) => $this->permission($context, $k), ARRAY_FILTER_USE_KEY);
        return $this->render($request, $context, 'imports', 'workspace.admin.imports', compact('current', 'recent', 'kinds'));
    }

    public function template(Request $request)
    {
        $context = $this->context($request);
        $kind = in_array($request->query('kind'), ['classes', 'students'], true) ? $request->query('kind') : 'people';
        abort_unless($this->permission($context, $kind), 403);
        $school = reset($context['schools']) ?: 'School name';
        $examples = match ($kind) {
            'people' => [['jordan.lee@example.com', 'Jordan', 'Lee', 'parent', $school], ['sam.patel@example.com', 'Sam', 'Patel', 'teacher', $school]],
            'classes' => [['Grade 4', 'Maple', 'sam.patel@example.com', $school]],
            default => [['Ella', 'Lee', 'Grade 4', 'Maple', $school, '', 'jordan.lee@example.com']],
        };
        return response()->streamDownload(function () use ($kind, $examples) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::KINDS[$kind][1]);
            foreach ($examples as $row) { fputcsv($out, $row); }
            fclose($out);
        }, 'ptm-'.$kind.'-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function upload(Request $request)
    {
        $context = $this->context($request);
        $data = $request->validate(['kind' => 'required|in:people,classes,students', 'file' => 'required|file|mimes:csv,txt|max:1024']);
        abort_unless($this->permission($context, $data['kind']), 403);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        $expected = self::KINDS[$data['kind']][1];
        $missing = array_diff(array_filter($expected, fn ($c) => !in_array($c, self::OPTIONAL, true) || ($c === 'school' && count($context['schools']) > 1)), $header);
        abort_if($missing, 422, 'The file is missing these columns: '.implode(', ', $missing).'. Download the template to see the format.');
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) { continue; }
            abort_if(count($rows) >= self::MAX_ROWS, 422, 'Import up to '.self::MAX_ROWS.' rows at a time. Split the file and upload each part.');
            $rows[] = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
        }
        fclose($handle);
        abort_unless($rows, 422, 'The file has no rows to import.');
        $checked = array_map(fn ($row) => $this->check($data['kind'], $context, $row), $rows);
        // The same admission number twice in one file would create a duplicate.
        $seen = [];
        foreach ($checked as &$row) {
            if ($data['kind'] === 'students' && $row['action'] === 'create' && $row['admission_no'] !== '') {
                if (isset($seen[$row['admission_no']])) { $row['action'] = 'error'; $row['note'] = 'This admission number appears earlier in the file.'; }
                $seen[$row['admission_no']] = true;
            }
        }
        unset($row);
        $id = DB::table('workspace_imports')->insertGetId(['user_id' => $request->user()->id, 'context' => $context['id'], 'kind' => $data['kind'], 'status' => 'preview',
            'rows' => json_encode($checked), 'summary' => json_encode($this->summarize($checked)), 'created_at' => now(), 'updated_at' => now()]);
        return redirect()->route('workspace.imports', ['context' => $context['id'], 'import' => $id]);
    }

    private function school(array $context, array $row): ?int
    {
        $value = trim((string) ($row['school'] ?? ''));
        if ($value === '' && count($context['schools']) === 1) { return (int) array_key_first($context['schools']); }
        if (ctype_digit($value) && isset($context['schools'][(int) $value])) { return (int) $value; }
        foreach ($context['schools'] as $id => $name) { if (mb_strtolower(trim($name)) === mb_strtolower($value)) { return (int) $id; } }
        return null;
    }

    private function checkPerson(array $context, array $row): array
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $role = strtolower(trim((string) ($row['role'] ?? '')));
        $out = ['email' => $email, 'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')), 'role' => $role, 'school_id' => $this->school($context, $row), 'school' => $row['school'] ?? ''];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return $out + ['action' => 'error', 'note' => 'Email address is not valid.']; }
        if (!in_array($role, ['parent', 'teacher', 'staff'], true)) { return $out + ['action' => 'error', 'note' => 'Role must be parent, teacher or staff.']; }
        if (!$out['school_id']) { return $out + ['action' => 'error', 'note' => 'School not found in your scope. Use the exact school name or ID.']; }
        $out['school'] = $context['schools'][$out['school_id']];
        $user = User::where('email', $email)->whereNull('deleted_at')->first();
        if (!$user) { return $out + ['action' => 'invite', 'note' => 'No account yet. An invitation will be emailed.']; }
        if ((int) $user->status !== 1 || !$user->email_verified_at) { return $out + ['action' => 'error', 'note' => 'The account is suspended or not verified.']; }
        $active = DB::table('workspace_memberships')->where('user_id', $user->id)->where('school_id', $out['school_id'])->where('role', $role)->where('status', 'active')->exists();
        if ($active) { return $out + ['action' => 'skip', 'note' => 'Already connected.']; }
        return $out + ['action' => 'connect', 'note' => 'Existing account will be connected.', 'user_id' => $user->id];
    }

    private function checkClass(array $context, array $row): array
    {
        $out = ['class' => trim((string) ($row['class'] ?? '')), 'section' => trim((string) ($row['section'] ?? '')), 'teacher_email' => strtolower(trim((string) ($row['teacher_email'] ?? ''))),
            'school_id' => $this->school($context, $row), 'school' => $row['school'] ?? ''];
        if ($out['class'] === '' || $out['section'] === '' || mb_strlen($out['class']) > 120 || mb_strlen($out['section']) > 120) { return $out + ['action' => 'error', 'note' => 'Class and section are required (up to 120 characters).']; }
        if (!$out['school_id']) { return $out + ['action' => 'error', 'note' => 'School not found in your scope.']; }
        $out['school'] = $context['schools'][$out['school_id']];
        $teacher = User::where('email', $out['teacher_email'])->where('status', 1)->whereNotNull('email_verified_at')->first();
        $isTeacher = $teacher && DB::table('workspace_memberships')->where('user_id', $teacher->id)->where('school_id', $out['school_id'])->where('role', 'teacher')->where('status', 'active')->exists();
        if (!$isTeacher) { return $out + ['action' => 'error', 'note' => 'The teacher must be an active teacher at this school. Import or add them first.']; }
        return $out + ['action' => 'create', 'note' => 'Class section will be created or its teacher updated.', 'teacher_id' => $teacher->id];
    }

    private function check(string $kind, array $context, array $row): array
    {
        return match ($kind) { 'people' => $this->checkPerson($context, $row), 'classes' => $this->checkClass($context, $row), default => $this->checkStudent($context, $row) };
    }

    private function checkStudent(array $context, array $row): array
    {
        $out = ['first_name' => trim((string) ($row['first_name'] ?? '')), 'last_name' => trim((string) ($row['last_name'] ?? '')), 'class' => trim((string) ($row['class'] ?? '')),
            'section' => trim((string) ($row['section'] ?? '')), 'admission_no' => trim((string) ($row['admission_no'] ?? '')), 'guardian_email' => strtolower(trim((string) ($row['guardian_email'] ?? ''))),
            'school_id' => $this->school($context, $row), 'school' => $row['school'] ?? ''];
        $out['name'] = trim($out['first_name'].' '.$out['last_name']);
        if ($out['first_name'] === '' || $out['last_name'] === '' || mb_strlen($out['first_name']) > 128 || mb_strlen($out['last_name']) > 128) { return $out + ['action' => 'error', 'note' => 'First and last name are required (up to 128 characters).']; }
        if (!$out['school_id']) { return $out + ['action' => 'error', 'note' => 'School not found in your scope.']; }
        $out['school'] = $context['schools'][$out['school_id']];
        $section = DB::table('class_sections as cs')->join('classes as c', 'c.id', '=', 'cs.class_id')->join('sections as s', 's.id', '=', 'cs.section_id')
            ->where('c.school_id', $out['school_id'])->whereNull('cs.deleted_at')->whereNull('c.deleted_at')->where('c.name', $out['class'])->where('s.name', $out['section'])->value('cs.id');
        if (!$section) { return $out + ['action' => 'error', 'note' => 'Class '.$out['class'].' / '.$out['section'].' does not exist at this school. Import or create it first.']; }
        $out['class_section_id'] = (int) $section;
        if ($out['admission_no'] !== '' && (mb_strlen($out['admission_no']) > 64 || DB::table('students')->where('admission_no', $out['admission_no'])->exists() || DB::table('users')->where('email', $out['admission_no'])->exists())) {
            return $out + ['action' => 'error', 'note' => 'Admission number is already used.'];
        }
        $note = 'Student will be added to '.$out['class'].' / '.$out['section'].'.';
        if ($out['guardian_email'] !== '') {
            $guardian = User::where('email', $out['guardian_email'])->where('status', 1)->whereNull('deleted_at')->first();
            $isParent = $guardian && DB::table('workspace_memberships')->where('user_id', $guardian->id)->where('school_id', $out['school_id'])->where('role', 'parent')->where('status', 'active')->exists();
            if ($isParent) { $out['guardian_id'] = $guardian->id; $note .= ' Linked to '.$out['guardian_email'].' as a verified guardian.'; }
            else { $note .= ' Guardian not linked: '.$out['guardian_email'].' is not a connected parent at this school yet.'; }
        }
        return $out + ['action' => 'create', 'note' => $note];
    }

    /** Creates a student record. Student accounts cannot sign in, and no password is ever emailed. */
    private function createStudent(\App\Models\User $actor, array $row): void
    {
        DB::transaction(function () use ($actor, $row) {
            $admission = $row['admission_no'] !== '' ? $row['admission_no'] : 'S'.$row['school_id'].'-'.strtoupper(bin2hex(random_bytes(4)));
            abort_if(DB::table('users')->where('email', $admission)->exists() || DB::table('students')->where('admission_no', $admission)->exists(), 422, 'Admission number is already used.');
            $user = User::forceCreate(['first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'email' => $admission, 'type' => 'student',
                'password' => \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(24))), 'image' => 'dummy_logo.jpg', 'status' => 1]);
            $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Student', 'web'));
            $studentId = DB::table('students')->insertGetId(['user_id' => $user->id, 'class_section_id' => $row['class_section_id'], 'admission_no' => $admission,
                'admission_date' => now()->toDateString(), 'class_status' => 'ACCEPTED', 'is_new_admission' => 1, 'created_at' => now(), 'updated_at' => now()]);
            if (!empty($row['guardian_id'])) {
                DB::table('workspace_guardians')->insertOrIgnore(['guardian_id' => $row['guardian_id'], 'student_id' => $studentId, 'school_id' => $row['school_id'],
                    'status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->access->audit($actor, $row['school_id'], 'student.imported', $studentId);
        });
    }

    private function summarize(array $rows): array
    {
        return collect($rows)->countBy('action')->all();
    }

    public function confirm(Request $request, int $id, WorkspaceInvitations $invitations)
    {
        $context = $this->context($request);
        $import = DB::table('workspace_imports')->where('id', $id)->where('user_id', $request->user()->id)->where('context', $context['id'])->where('status', 'preview')->first();
        abort_unless($import, 404);
        abort_unless($this->permission($context, $import->kind), 403);
        // Re-check every row: accounts and memberships may have changed since the preview.
        $rows = array_map(function ($row) use ($context, $import) {
            $source = ['school' => (string) ($row['school_id'] ?? '')] + $row;
            if ($import->kind === 'people') { return ['name' => $row['name'] ?? ''] + $this->checkPerson($context, $source); }
            // Rows flagged in the preview (such as duplicates within the file) stay excluded.
            return ($row['action'] ?? '') === 'error' ? $row : $this->check($import->kind, $context, $source);
        }, json_decode($import->rows, true));
        $emailReady = (bool) config('delivery.email_enabled');
        foreach ($rows as &$row) {
            try {
                if ($row['action'] === 'connect') {
                    DB::table('workspace_memberships')->updateOrInsert(['user_id' => $row['user_id'], 'school_id' => $row['school_id'], 'role' => $row['role']],
                        ['status' => 'active', 'approved_by' => $request->user()->id, 'starts_at' => now(), 'ends_at' => null, 'permissions' => '[]', 'created_at' => now(), 'updated_at' => now()]);
                    $this->access->audit($request->user(), $row['school_id'], 'membership.connected', $row['user_id'].':'.$row['role']);
                    $row['result'] = 'Connected';
                } elseif ($row['action'] === 'invite') {
                    if (!$emailReady) { $row['result'] = 'Not sent: email delivery is not set up'; continue; }
                    $invitations->send($request->user(), $row['school_id'], $row['email'], $row['role']);
                    $row['result'] = 'Invitation sent';
                } elseif ($row['action'] === 'create' && $import->kind === 'students') {
                    $this->createStudent($request->user(), $row);
                    $row['result'] = 'Added'.(!empty($row['guardian_id']) ? ' and linked to guardian' : '');
                } elseif ($row['action'] === 'create') {
                    DB::transaction(function () use ($request, $row) {
                        $class = \App\Models\ClassSchool::firstOrCreate(['school_id' => $row['school_id'], 'name' => $row['class']]);
                        $section = \App\Models\Section::firstOrCreate(['school_id' => $row['school_id'], 'name' => $row['section']]);
                        $cs = \App\Models\ClassSection::updateOrCreate(['class_id' => $class->id, 'section_id' => $section->id], ['class_teacher_id' => $row['teacher_id']]);
                        $this->access->audit($request->user(), $row['school_id'], 'class.assignment.saved', $cs->id);
                    });
                    $row['result'] = 'Saved';
                } else {
                    $row['result'] = $row['action'] === 'skip' ? 'Skipped: '.$row['note'] : 'Not imported: '.$row['note'];
                }
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $row['result'] = 'Failed: '.$e->getMessage();
            }
        }
        unset($row);
        $summary = collect($rows)->countBy(fn ($r) => str_starts_with($r['result'] ?? '', 'Connected') || str_starts_with($r['result'] ?? '', 'Invitation sent') || ($r['result'] ?? '') === 'Saved' || str_starts_with($r['result'] ?? '', 'Added') ? 'done' : 'not_done')->all();
        DB::table('workspace_imports')->where('id', $id)->update(['status' => 'completed', 'rows' => json_encode($rows), 'summary' => json_encode($summary), 'updated_at' => now()]);
        $this->access->audit($request->user(), count($context['schools']) === 1 ? array_key_first($context['schools']) : null, 'import.completed', $id.':'.$import->kind);
        return redirect()->route('workspace.imports', ['context' => $context['id'], 'import' => $id])
            ->with('workspace_success', 'Import finished: '.($summary['done'] ?? 0).' done, '.($summary['not_done'] ?? 0).' not imported. See each row below.');
    }

    public function discard(Request $request, int $id)
    {
        $context = $this->context($request);
        DB::table('workspace_imports')->where('id', $id)->where('user_id', $request->user()->id)->where('context', $context['id'])->where('status', 'preview')->update(['status' => 'discarded', 'updated_at' => now()]);
        return redirect()->route('workspace.imports', ['context' => $context['id']])->with('workspace_success', 'Import discarded. Nothing was changed.');
    }
}
