@extends('layouts.master')

@section('title')
{{ __('class') }} {{__('teacher')}}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="car\`qd-title">
                        {{ __('assign') . ' ' . __('class') . ' ' . __('teacher') }}
                    </h4>
                    <div class="row">
                        <div class="col-12">


                            <div id="toolbar">
                                <select name="filter_class_id" id="filter_class_id" class="form-control">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach ($classes as $class)
                                    <option value={{ $class->id }}>
                                        {{ $class->name  }}</option>
                                    @endforeach
                                </select>



                            </div>
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table" data-url="{{ url('class-teacher-list') }}" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id" data-query-params="AssignTeacherQueryParams" data-sort-order="desc" data-maintain-selected="true" data-export-types='["txt","excel"]' data-export-options='{ "fileName": "data-list-<?= date(' d-m-y') ?>" }'
                                >
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}
                                        </th>
                                        <th scope="col" data-field="class" data-sortable="false">
                                            {{ __('class') }}</th>
                                        <th scope="col" data-field="section" data-sortable="false">
                                            {{ __('section') }}</th>
                                        <th scope="col" data-field="teacher" data-sortable="false">
                                            {{ __('teacher') }}</th>
                                        <th data-events="actionEvents" scope="col" data-field="operate" data-sortable="false">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">
                            {{ __('edit') . ' ' . __('class') . ' ' . __('teacher') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form class="edit-class-teacher-form" action="{{ route('class.teacher.store') }}" novalidate="novalidate">
                        @csrf
                        <div class="modal-body">
                            <div class="row form-group">
                                <div class="form-group col-sm-12 col-md-12">
                                    {{-- hidden input to store id --}}
                                    <input type="hidden" name="class_section_id" id="class_section_id_value">

                                    <label>{{ __('class') }} {{ __('section') }} <span class="text-danger">*</span></label>
                                    <select name="class_section_id_select" id="class_section_id" class="form-control" disabled>
                                        @foreach ($class_section as $section)
                                        <option value="{{ $section->id }}">
                                            {{ $section->class->name . ' ' . $section->section->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="form-group col-sm-12 col-md-12">
                                    <label>{{ __('teacher') }} <span class="text-danger">*</span></label>
                                    <select name="teacher_id" id="teacher_id" class="form-control">
                                        @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->user_id }}">
                                            {{ $teacher->user->first_name . ' ' . $teacher->user->last_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group">
                                <a style="cursor: pointer" id="remove_class_teacher" class="ml-4">{{__('click_here_to_remove_class_teacher')}} :- <span id="teacher_name"></span> </a>
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
    </div>
</div>
@endsection

@section('script')
<script>
    window.actionEvents = {
            'click .editdata': function(e, value, row, index) {
                $('#class_section_id').val(row.id);

                //hidden input to store id
                $('#class_section_id_value').val(row.id);

                $('#teacher_id').val(row.teacher_id);
                if(row.teacher_id != null){
                    $('#remove_class_teacher').show();
                    $('#teacher_name').html(row.teacher)

                    $('#remove_class_teacher').on('click', function () {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "Remove Class Teacher!",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Confirm!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                let url = baseUrl + '/remove-class-teacher/' + row.id;
                                function successCallback(response) {
                                    $.toast({
                                        text: response.message,
                                        icon: 'success',
                                        loader:false,
                                        position: 'top-right',
                                    });
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
                                }
                                function errorCallback(response) {
                                    showErrorToast(response.message);
                                }
                                ajaxRequest('POST', url, null, null, successCallback, errorCallback);
                            }
                        });
                    });
                }else{
                    $('#remove_class_teacher').hide();
                }
            }
        };
</script>
@endsection
