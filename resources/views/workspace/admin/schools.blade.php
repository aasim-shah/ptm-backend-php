@extends('workspace.layout')
@section('workspace-content')
@php $count = fn ($id, $role) => (int) optional(($members[$id] ?? collect())->firstWhere('role', $role))->total; @endphp
<x-ap.head :eyebrow="$context['label'].' / Schools'" title="Schools" :sub="$canCreate ? 'Every school on the platform. Create a school, assign its School Admin, and suspend or reactivate it.' : 'Schools in '.$context['organization'].'.'">
    @if($access->can($context, 'reports.export'))<a class="ws-btn ap-btn-ghost" href="{{ route('workspace.reports', ['context' => $context['id'], 'type' => 'schools']) }}"><i class="fa fa-download" aria-hidden="true"></i>Export</a>@endif
    @if($canCreate)<button type="button" class="ws-btn" data-dialog-open="add-school"><i class="fa fa-plus" aria-hidden="true"></i>Add school</button>@endif
</x-ap.head>

<x-ap.stats :stats="$stats" />

<section class="ap-panel">
    <x-ap.toolbar :action="route('workspace.schools')" :context="$context" placeholder="Search by name, town, postcode or email" :count="$schools->total()">
        @if($districts->count())<select name="district_id" aria-label="District"><option value="">All districts</option>@foreach($districts as $d)<option value="{{ $d->id }}" @selected((string) request('district_id') === (string) $d->id)>{{ $d->name }}</option>@endforeach</select>@endif
        <select name="market" aria-label="Market"><option value="">All markets</option><option value="US" @selected(request('market') === 'US')>United States</option><option value="UK" @selected(request('market') === 'UK')>United Kingdom</option><option value="none" @selected(request('market') === 'none')>Market not set</option></select>
        <select name="status" aria-label="Status"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select>
    </x-ap.toolbar>
    <div class="ap-table-wrap"><table class="ap-table">
        <thead><tr><th>School</th><th>Market</th><th>District</th><th class="ap-num">Admins</th><th class="ap-num">Teachers</th><th class="ap-num">Parents</th><th class="ap-num">Students</th><th>Status</th><th class="ap-actions ap-keep"></th></tr></thead>
        <tbody>
        @forelse($schools as $school)
            @php $href = route('workspace.school', ['id' => $school->id, 'context' => $context['id']]); @endphp
            <tr>
                <td><a href="{{ $href }}"><strong>{{ $school->school_name }}</strong></a><div class="ap-muted">{{ collect([$school->address, $school->post_town, $school->post_code])->filter()->implode(', ') }}</div></td>
                <td>@if($school->market)<span class="ap-market ap-market--{{ $school->market }}">{{ $school->market }}</span>@else<span class="ap-muted">Not set</span>@endif</td>
                <td>{{ $school->district_name ?? '—' }}</td>
                <td class="ap-num">@if($count($school->id, 'school_admin')){{ $count($school->id, 'school_admin') }}@else<x-ap.pill status="warn" label="None" />@endif</td>
                <td class="ap-num">{{ $count($school->id, 'teacher') }}</td>
                <td class="ap-num">{{ $count($school->id, 'parent') }}</td>
                <td class="ap-num">{{ number_format($students[$school->id] ?? 0) }}</td>
                <td><x-ap.pill :status="$school->status ?? 'active'" /></td>
                <td class="ap-actions ap-keep"><a href="{{ $href }}">Open →</a></td>
            </tr>
        @empty
            <x-ap.empty colspan="9" icon="university" :title="request()->except(['context','page']) ? 'No schools match these filters' : 'No schools yet'">
                <p>{{ $canCreate ? 'Add the first school to start onboarding.' : 'Your Super Admin assigns schools to you.' }}</p>
            </x-ap.empty>
        @endforelse
        </tbody>
    </table></div>
    <x-ap.pager :items="$schools" />
</section>

@if($canCreate)
<x-ap.dialog id="add-school" title="Add a school" sub="Create the school, then assign its School Admin from the school page.">
    <form method="post" action="{{ route('workspace.school-create') }}" class="ap-form">@csrf
        <input type="hidden" name="context" value="{{ $context['id'] }}"><input type="hidden" name="_dialog" value="add-school">
        <label class="ap-span">School name<input name="school_name" required maxlength="191" value="{{ old('school_name') }}" placeholder="e.g. Lincoln Elementary School"></label>
        <label>Market<select name="market" required><option value="">Choose</option>@foreach(\App\Http\Controllers\Workspace\AdministrationController::MARKETS as $code => $name)<option value="{{ $code }}" @selected(old('market') === $code)>{{ $name }}</option>@endforeach</select></label>
        <label>District <small>(optional)</small><select name="district_id"><option value="">No district</option>@foreach($districts as $d)<option value="{{ $d->id }}" @selected((string) old('district_id') === (string) $d->id)>{{ $d->name }} · {{ $d->market }}</option>@endforeach</select></label>
        <label class="ap-span">Street address<input name="address" required maxlength="191" value="{{ old('address') }}"></label>
        <label>Town or city<input name="post_town" maxlength="191" value="{{ old('post_town') }}"></label>
        <label>State / county<input name="state" maxlength="191" value="{{ old('state') }}"></label>
        <label>ZIP code / postcode<input name="post_code" required maxlength="32" value="{{ old('post_code') }}"></label>
        <label>Time zone<select name="timezone"><option value="">Use the market default</option>@foreach(\DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA | \DateTimeZone::EUROPE) as $tz)<option value="{{ $tz }}" @selected(old('timezone') === $tz)>{{ $tz }}</option>@endforeach</select></label>
        <label>School email<input type="email" name="email" maxlength="191" value="{{ old('email') }}"></label>
        <label>Phone<input name="phone" maxlength="40" value="{{ old('phone') }}"></label>
        <label class="ap-span">Website<input type="url" name="website" maxlength="191" value="{{ old('website') }}" placeholder="https://"></label>
        <div class="ap-form-actions"><button type="button" class="ws-btn ap-btn-ghost" data-dialog-close>Cancel</button><button class="ws-btn" type="submit">Create school</button></div>
    </form>
</x-ap.dialog>
@endif
@endsection
