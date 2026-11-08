@extends('workspace.layout')
@section('workspace-content')
@php
    $readOnly = !$canWrite;
    $schoolNames = $context['schools'];
@endphp
<x-ap.head :eyebrow="$context['label'].' / Students'" title="Students"
    :sub="($readOnly ? 'A read-only roster' : 'Students you are authorized to support').' '.(\App\Services\WorkspaceAccess::platformWide($context) ? 'across the platform' : 'in '.$context['organization']).'. Guardian status shows who can see each student’s published updates.'">
    @if($access->can($context, 'reports.export'))<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => 'students']) }}"><i class="fa fa-download" aria-hidden="true"></i>Export</a>@endif
</x-ap.head>

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.students')" :context="$context" placeholder="Search by student name or admission number" :count="$students->total()">
        @if(count($schoolNames) > 1)<select name="school_id" aria-label="School"><option value="">All schools</option>@foreach($schoolNames as $id => $name)<option value="{{ $id }}" @selected((string) request('school_id') === (string) $id)>{{ $name }}</option>@endforeach</select>@endif
        <select name="class_id" aria-label="Class"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected((string) request('class_id') === (string) $class->id)>{{ $class->name }}@if(count($schoolNames) > 1) · {{ $schoolNames[$class->school_id] ?? '' }}@endif</option>@endforeach</select>
        <select name="guardians" aria-label="Guardian status"><option value="">Any guardian status</option><option value="verified" @selected(request('guardians') === 'verified')>Has a verified guardian</option><option value="pending" @selected(request('guardians') === 'pending')>Guardian request waiting</option><option value="none" @selected(request('guardians') === 'none')>No verified guardian</option></select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>Student</th><th>School</th><th>Class / section</th><th>Enrollment</th><th class="ap-num">Verified guardians</th>@if($canWrite)<th class="ap-actions ap-keep"></th>@endif</tr></thead>
        <tbody>
        @forelse($students as $student)
            @php $status = $student->class_status ? strtoupper($student->class_status) : 'ACCEPTED'; @endphp
            <tr>
                <td><x-ap.person :first="$student->user?->first_name" :last="$student->user?->last_name" :email="$student->admission_no ? 'Admission no. '.$student->admission_no : null" /></td>
                <td>{{ $schoolNames[$student->class_section?->class?->school_id] ?? '—' }}</td>
                <td>{{ $student->class_section?->class?->name ?? '—' }}@if($student->class_section?->section) <span class="ap-muted">/ {{ $student->class_section->section->name }}</span>@endif</td>
                <td><x-ap.pill :status="$status === 'ACCEPTED' ? 'active' : ($status === 'PENDING' ? 'pending' : 'none')" :label="\App\Http\Controllers\Workspace\DirectoryController::STUDENT_STATUS[$status] ?? ucfirst(strtolower($status))" /></td>
                <td class="ap-num">{{ (int) $student->verified_guardians }}@if($student->pending_guardians) <span class="ap-chip" title="Waiting for verification">+{{ $student->pending_guardians }} waiting</span>@endif</td>
                @if($canWrite)<td class="ap-actions ap-keep"><a href="{{ route('workspace.page', ['page' => 'updates', 'context' => $context['id'], 'student_id' => $student->id]) }}">Write update →</a></td>@endif
            </tr>
        @empty
            <x-ap.empty :colspan="$canWrite ? 6 : 5" icon="graduation-cap" title="No students match">
                <p>{{ request()->except(['context','page']) ? 'Try a different search or clear the filters.' : 'Students appear here once schools add them to classes.' }}</p>
            </x-ap.empty>
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$students" />
</section>
@if($readOnly)<p class="ws-meta"><i class="fa fa-lock" aria-hidden="true"></i> Student updates are written by teachers and School Admins. This view never shows private academic data.</p>@endif
@endsection
