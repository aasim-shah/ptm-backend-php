@extends('layouts.master')

@section('title')
{{ __('subject') . ' ' . __('teacher') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('subject') . ' ' . __('teacher') }}
        </h3>
    </div>

    <div class="row">
        @can('subject-teacher-create')
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('assign') . ' ' . __('subject') . ' ' . __('teacher') }}
                    </h4>
                    <form class="assign_subject_teacher pt-3" action="{{ url('subject-teachers') }}" method="POST" novalidate="novalidate">
                        @csrf
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('class') }} {{ __('section') }} <span class="text-danger">*</span></label>
                                <select name="class_section_id" id="class_section_id" class="class_section_id form-control" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach ($class_section as $section)
                                    <option value="{{$section->id}}" data-class="{{ $section->class->id }}"> {{ $section->class->name . ' ' . $section->section->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('subject') }} <span class="text-danger">*</span></label>
                                <select name="subject_id" id="subject_id" class="subject_id form-control" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach ($subjects as $subject)
                                    <option value="{{$subject->id}}"> {{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('teacher') }} <span class="text-danger">*</span></label>
                                <select multiple name="teacher_id[]" id="teacher_id" class="form-control js-example-basic-single select2-hidden-accessible" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    @foreach ($teachers as $teacher)
                                    <option value="{{$teacher->user_id}}"> {{ $teacher->user->name  }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <input class="btn btn-theme" type="submit" value={{ __('submit') }}>
                    </form>
                </div>
            </div>
        </div>
        @endcan

        @can('subject-teacher-list')
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('list') . ' ' . __('subject') . ' ' . __('teacher') }}
                    </h4>
                    <div class="row">

                        <div id="toolbar" class="row">
                            <div class="col-md-4">

                                <select name="filter_class_section_id" id="filter_class_section_id" class="form-control">
                                    <option value="">{{ __('select_class_section') }}</option>
                                    @foreach ($class_section as $class)
                                    <option value={{ $class->id }}>
                                        {{ $class->class->name . ' ' . $class->section->name  }}
                                    </option>
                                    @endforeach
                                </select>

                            </div>

                            <div class="col-md-4">

                                <select name="filter_teacher_id" id="filter_teacher_id" class="form-control">
                                    <option value="">{{ __('select_teacher') }}</option>
                                    @foreach ($teachers as $teacher)
                                    <option value={{ $teacher->user_id }}>
                                        {{ $teacher->user->first_name . ' ' . $teacher->user->last_name }}
                                    </option>
                                    @endforeach
                                </select>

                            </div>

                            <div class="col-md-4">

                                <select name="filter_subject_id" id="filter_subject_id" class="form-control">
                                    <option value="">{{ __('select_subject') }}</option>
                                    @foreach ($subjects as $subject)
                                    <option value={{ $subject->id }}>
                                        {{ $subject->name }}
                                    </option>
                                    @endforeach
                                </select>

                            </div>

                        </div>
                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table" data-url="{{ url('subject-teachers-list') }}" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-export-types='["txt","excel"]' data-export-options='{ "fileName": "session-year-list-<?= date(' d-m-y') ?>
                                ","ignoreColumn": ["operate"]}'
                                data-query-params="AssignSubjectTeacherQueryParams">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}
                                        </th>
                                        <th scope="col" data-field="class_section_id" data-sortable="false" data-visible="false">{{ __('class_section_id') }}</th>
                                        <th scope="col" data-field="class_section_name" data-sortable="false">
                                            {{ __('class') . ' ' . __('section') . ' ' . __('name') }}</th>
                                        <th scope="col" data-field="subject_id" data-sortable="true" data-visible="false">{{ __('subject_id') }}</th>
                                        <th scope="col" data-field="subject_name" data-sortable="false">
                                            {{ __('subject') . ' ' . __('name') }}</th>
                                        <th scope="col" data-field="teacher_id" data-sortable="true" data-visible="false">{{ __('teacher_id') }}</th>
                                        <th scope="col" data-field="teacher_name" data-sortable="false">
                                            {{ __('teacher') . ' ' . __('name') }}</th>
                                        @canany(['subject-teacher-edit', 'subject-teacher-delete'])
                                        <th data-events="actionEvents" scope="col" data-field="operate" data-sortable="false">{{ __('action') }}</th>
                                        @endcanany
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endcan
    </div>
</div>


<div class="modal fade" id="editModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">
                    {{ __('edit') . ' ' . __('subject') . ' ' . __('teacher') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <form id="formdata" class="editform" action="{{ url('subject-teachers') }}" novalidate="novalidate">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="id">
                    <div class="row">
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('class') }} {{ __('section') }} <span class="text-danger">*</span></label>
                            <select name="class_section_id" id="edit_class_section_id" class="class_section_id form-control select2" style="width:100%;">
                                @foreach ($class_section as $section)
                                <option value="{{ $section->id }}" data-class="{{ $section->class->id }}">
                                    {{ $section->class->name }} - {{ $section->section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('subject') }} <span class="text-danger">*</span></label>
                            <select name="subject_id" id="edit_subject_id" class="subject_id form-control select2" style="width:100%;">
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('teacher') }} <span class="text-danger">*</span></label>
                            <select name="teacher_id" id="edit_teacher_id" class="form-control select2" style="width:100%;">
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"data-dismiss="modal">{{ __('close') }}</button>
                    <input class="btn btn-theme" type="submit" value={{ __('submit') }} />
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    window.actionEvents = {
        'click .editdata': function (e, value, row, index) {
            $('#id').val(row.id);
            $('#edit_class_section_id').val(row.class_section_id).trigger('change',row.subject_id);
            setTimeout(() => {
                $('#edit_subject_id').val(row.subject_id).trigger('change');
                setTimeout(() => {
                    $('#edit_teacher_id').val(row.teacher_id).trigger('change');
                }, 500);
            }, 1000);
        }
    };
</script>
@endsection
