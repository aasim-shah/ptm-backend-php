@extends('layouts.master')

@section('title')
{{ __('manage') . ' ' . __('exam') . ' ' . __('timetable') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('exam') . ' ' . __('timetable') }}
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <h4 class="page-title mb-4">
                        {{ __('create') . ' ' . __('exam') . ' ' . __('timetable') }}
                    </h4>
                    <div class="form-group">
                        <form class="create_exam_timetable_form" action="{{ url('exam-timetable') }}" method="POST">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>{{ __('exam') }} </label>
                                    <select name="exam_id" id="exam_options" class="form-control" required>
                                        <option value="">--{{ __('select') }}--</option>
                                        @foreach ($exams as $exam)
                                        <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>{{ __('class') }} </label>
                                    <select name="class_id" id="exam_classes_options" class="form-control" required>
                                        <option value="">--{{ __('select') }}--</option>
                                    </select>
                                </div>
                            </div>

                            <div class="exam_timetable_content">
                                <div class="row">
                                    <input type="hidden" name="timetable[0][timetable_id]" class="timetable_id form-control" required>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('subject') }} </label>
                                        <select name="timetable[0][subject_id]" class="form-control exam_subjects_options" required>
                                            <option value="">--{{ __('select') }}--</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('total_marks') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="timetable[0][total_marks]" class="total_marks form-control" placeholder="{{ __('total_marks') }}" min="1" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('passing_marks') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="timetable[0][passing_marks]" class="passing_marks form-control" placeholder="{{ __('passing_marks') }}" min="1" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>{{ __('start_time') }} <span class="text-danger">*</span></label>
                                        <input type="time" name="timetable[0][start_time]" class="start_time form-control" placeholder="{{ __('start_time') }}" autocomplete="off" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('end_time') }} <span class="text-danger">*</span></label>
                                        <input type="time" name="timetable[0][end_time]" class="end_time form-control" placeholder="{{ __('end_time') }}" autocomplete="off" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>{{ __('date') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="timetable[0][date]" class="datepicker-popup form-control" placeholder="{{ __('date') }}" autocomplete="off" required>
                                    </div>
                                    <div class="form-group col-md-1 pl-0 mt-4">
                                        <button type="button" class="btn btn-inverse-success btn-icon add-exam-timetable-content">
                                            <i class="fa fa-plus"></i></button>
                                    </div>
                                    <div class="col-12">
                                        <hr>
                                    </div>
                                </div>
                            </div>

                            {{-- container for adding multiple subjects time table when "+" btn is clicked --}}
                            <div class="extra-timetable"></div>

                            <input type="submit" class="btn btn-theme" value={{ __('submit') }} />
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('list') . ' ' . __('exam') . ' ' . __('timetable') }}
                    </h4>
                    <div id="toolbar" class="row exam_class_filter">

                        <div class="col">
                            <label for="filter_exam_name">
                                {{ __('exam') }}
                            </label>
                            <select name="filter_exam_name" id="filter_exam_name" class="form-control">
                                <option value="">All</option>
                                @foreach ($exams as $exam)
                                <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col">
                            <label for="filter_class_name">
                                {{ __('class') }}
                            </label>
                            <select name="filter_class_name" id="filter_class_name" class="form-control">
                                <option value="">All</option>
                                @foreach ($class_name as $class)
                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table" data-url="{{ route('exam-timetable.show', 1) }}" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-query-params="ExamClassQueryParams" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-export-types='["txt","excel"]' data-export-options='{ "fileName": "exam-timetable-list-<?= date(' d-m-y') ?>","ignoreColumn":["operate"]}' data-show-export="true" data-detail-formatter="examListFormatter">
                        <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-visible="false"> {{ __('id') }}</th>
                                <th scope="col" data-field="no">{{ __('no.') }}</th>
                                <th scope="col" data-field="exam_name" data-sortable="false">{{ __('exam') }} {{ __('name') }}</th>
                                <th scope="col" data-field="class_name" data-sortable="false">{{ __('class') }} </th>
                                <th scope="col" data-field="timetable" data-formatter="examTimetableFormatter">{{ __('timetable') }} </th>
                                <th scope="col" data-field="session_year">{{ __('session_years') }}</th>
                                <th scope="col" data-field="created_at" data-sortable="true" data-visible="false">{{ __('created_at') }}</th>
                                <th scope="col" data-field="updated_at" data-sortable="true" data-visible="false">{{ __('updated_at') }}</th>
                                <th scope="col" data-field="operate" data-events="examTimetableEvents">{{ __('action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">
                            {{ __('edit') . ' ' . __('exam'). ' ' . __('timetable') }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="edit_exam_timetable_tamplate" style="display:none">
                            <div class="row">
                                <input type="hidden" name="edit_timetable[0][timetable_id]" class="edit_timetable_id form-control" required>
                                <div class="form-group col-md-4">
                                    <label>{{ __('subject') }} </label> <span class="text-danger">*</span></label>
                                    <select name="edit_timetable[0][subject_id]" class="form-control edit_exam_subjects_options" required></select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>{{ __('total_marks') }} <span class="text-danger">*</span></label>
                                    <input type="number" name="edit_timetable[0][total_marks]" class="edit_total_marks form-control" placeholder="{{ __('total_marks') }}" min="1" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>{{ __('passing_marks') }} <span class="text-danger">*</span></label>
                                    <input type="number" name="edit_timetable[0][passing_marks]" class="edit_passing_marks form-control" placeholder="{{ __('passing_marks') }}" min="1" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>{{ __('start_time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="edit_timetable[0][start_time]" class="edit_start_time form-control" placeholder="{{ __('start_time') }}" autocomplete="off" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>{{ __('end_time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="edit_timetable[0][end_time]" class="edit_end_time form-control" placeholder="{{ __('end_time') }}" autocomplete="off" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>{{ __('date') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="edit_timetable[0][date]" class="datepicker-popup edit_date form-control" placeholder="{{ __('date') }}" autocomplete="off" required>
                                </div>
                                <div class="form-group col-md-1 pl-0 mt-4">
                                    <button type="button" class="btn btn-inverse-danger btn-icon remove-edit-exam-timetable-content">
                                        <i class="fa fa-times"></i></button>
                                </div>
                                <div class="col-12">
                                    <hr>
                                </div>
                            </div>
                        </div>
                        <form class="pt-3 edit-form-timetable" action="{{ url('exams/update-timetable') }}" novalidate="novalidate">
                            <input type="hidden" name="exam_id" class="edit_timetable_exam_id form-control" required>
                            <input type="hidden" name="class_id" class="edit_timetable_class_id form-control" required>
                            <input type="hidden" name="session_year_id" class="edit_timetable_session_year_id form-control" required>

                            <div class="edit-timetable-container"></div>
                            <div class="col-md-4 pl-0 mb-4">
                                <button type="button" class="btn btn-inverse-success add-new-timetable-data" title="Add new row">
                                    Add New Data
                                </button>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('close')
                            }}</button>
                        <input class="btn btn-theme" type="submit" value={{ __('edit') }} />
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
