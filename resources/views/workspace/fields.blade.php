@csrf
<input type="hidden" name="context" value="{{ $context['id'] }}">
@if(count($context['schools']) === 1)
    <input type="hidden" name="school_id" value="{{ array_key_first($context['schools']) }}">
@else
    <label>School<select name="school_id" required><option value="">Choose a school</option>@foreach($context['schools'] as $id => $name)<option value="{{ $id }}" @selected((string)old('school_id') === (string)$id)>{{ $name }}</option>@endforeach</select></label>
@endif
