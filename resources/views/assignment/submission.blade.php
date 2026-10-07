@extends('layouts.master')

@section('title')
    {{ __('manage') . ' ' . __('assignment') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage') . ' ' . __('assignment_submission') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list') . ' ' . __('assignment_submission') }}
                        </h4>

                        <div id="toolbar">
                            <select name="filter_subject_id" id="filter_subject_id" class="form-control">
                                <option value="">{{ __('all') }}</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}">
                                        {{ $subject->name }} - {{$subject->type}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table"
                            data-url="{{ route('assignment.submission.list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1"
                            data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                            data-query-params="AssignmentSubmissionQueryParams" data-sort-order="desc"
                            data-maintain-selected="true" data-export-types='["txt","excel"]'
                            data-export-options='{ "fileName": "assignment-submission-list-<?= date('d-m-y') ?>"
                            ,"ignoreColumn": ["operate"]}'
                            data-show-export="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}</th>
                                    <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                    <th scope="col" data-field="assignment_name" data-sortable="true">
                                        {{ __('assignment_name') }}</th>
                                    <th scope="col" data-field="subject" data-sortable="true">{{ __('subject') }}</th>
                                    <th scope="col" data-field="student_name" data-sortable="true">
                                        {{ __('student_name') }}</th>
                                    <th scope="col" data-field="file" data-sortable="true"
                                        data-formatter="fileFormatter">{{ __('files') }}</th>
                                    <th scope="col" data-field="status" data-sortable="true"
                                        data-formatter="assignmentSubmissionStatusFormatter">{{ __('status') }}</th>
                                    <th scope="col" data-field="points" data-sortable="true" data-visible="true">
                                        {{ __('points') }}</th>
                                    <th scope="col" data-field="feedback" data-sortable="true">{{ __('feedback') }}
                                    </th>
                                    <th scope="col" data-field="session_year_id" data-sortable="true"
                                        data-visible="false">{{ __('session_year_id') }}</th>
                                    <th scope="col" data-field="created_at" data-sortable="true" data-visible="false">
                                        {{ __('created_at') }}</th>
                                    <th scope="col" data-field="updated_at" data-sortable="true" data-visible="false">
                                        {{ __('updated_at') }}</th>
                                    <th scope="col" data-field="operate" data-sortable="false"
                                        data-events="assignmentSubmissionEvents">{{ __('action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">
                                {{ __('edit') . ' ' . __('assignment_submission') }}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form class="pt-3 class-edit-form" id="edit-form" action="{{ url('assignment-submission') }}"
                            novalidate="novalidate">
                            <input type="hidden" name="edit_id" id="edit_id" value="" />
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>{{ __('assignment_name') }}</label>
                                    <input type="text" name="" id="assignment_name" class="form-control"
                                        disabled>
                                </div>

                                <div class="form-group">
                                    <label>{{ __('subject') }}</label>
                                    <input type="text" name="" id="subject" class="form-control" disabled>
                                </div>

                                <div class="form-group">
                                    <label>{{ __('student_name') }}</label>
                                    <input type="text" name="" id="student_name" class="form-control"
                                        disabled>
                                </div>

                                <div class="form-group">
                                    <label>{{ __('files') }}</label>
                                    <div id="files"></div>
                                </div>

                                <div class="form-group">
                                    <label>{{ __('status') }} <span class="text-danger">*</span></label>
                                    <div class="d-flex">
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="status"
                                                    id="status_accept" value="1">{{ __('accept') }}
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="status"
                                                    id="status_reject" value="2">{{ __('reject') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" id="points_div">
                                    <label>{{ __('points') }} <span id="assignment_points"></span></label>
                                    <input type="number" name="points" id="points" class="form-control"
                                        min="0">
                                </div>

                                <div class="form-group">
                                    <label>{{ __('feedback') }}</label>
                                    {!! Form::textarea('feedback', null, ['class' => 'form-control', 'id' => 'feedback']) !!}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-dismiss="modal">{{ __('close') }}</button>
                                <input class="btn btn-theme" type="submit" value={{ __('edit') }} />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
