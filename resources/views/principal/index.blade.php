@extends('layouts.master')

@section('title')
{{ __('principal') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('principal') }}
        </h3>
    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('new_principal') }}
                    </h4>
                    <form class="create-form pt-3" id="formdata" action="{{ route('principal.store') }}"
                          enctype="multipart/form-data" method="POST" novalidate="novalidate">
                        @csrf
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('first_name') }} <span class="text-danger">*</span></label>
                                {!! Form::text('first_name', null, ['required', 'placeholder' => __('first_name'), 'class' => 'form-control']) !!}

                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('last_name') }} <span class="text-danger">*</span></label>
                                {!! Form::text('last_name', null, ['required', 'placeholder' => __('last_name'), 'class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('password') }} <span class="text-danger">*</span></label>
                                {!! Form::text('password', null, ['required', 'placeholder' => __('password'), 'class' => 'form-control']) !!}

                            </div>

                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('gender') }} <span class="text-danger">*</span></label><br>
                                <div class="d-flex">
                                    <div class="form-check form-check-inline">
                                        <label class="form-check-label">
                                            {!! Form::radio('gender', 'male') !!}
                                            {{ __('male') }}
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <label class="form-check-label">
                                            {!! Form::radio('gender', 'female') !!}
                                            {{ __('female') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('email') }} <span class="text-danger">*</span></label>
                                {!! Form::text('email', null, ['required', 'placeholder' => __('email'), 'class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('mobile') }} <span class="text-danger">*</span></label>
                                {!! Form::number('mobile', null, ['required', 'placeholder' => __('mobile'), 'class' => 'form-control']) !!}

                            </div>
                            <div class="form-group col-sm-12 col-md-6">

                                <label>{{ __('image') }}</label>
                                <input type="file" name="image" class="file-upload-default" accept="image/png,image/jpeg,image/jpg" />
                                <div class="input-group col-xs-12">
                                    <input type="text" class="form-control file-upload-info" disabled=""
                                           placeholder="{{ __('image') }}" />
                                    <span class="input-group-append">
                                            <button class="file-upload-browse btn bight-pink"
                                                    type="button">{{ __('upload') }}</button>
                                        </span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('dob') }} <span class="text-danger">*</span></label>
                                {!! Form::text('dob', null, ['required', 'placeholder' => __('dob'), 'class' => 'datepicker-popup-no-future form-control']) !!}
                                <span class="input-group-addon input-group-append">
                                    </span>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('qualification') }} <span class="text-danger">*</span></label>
                                {!! Form::textarea('qualification', null, ['required', 'placeholder' => __('qualification'), 'class' => 'form-control', 'rows' => 3]) !!}
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('schools') }} <span class="text-danger">*</span></label>
                                <select name="school_id" id="school_id" class="form-control select2">
                                    <option value="">{{ __('select') . ' '. __('school') }}</option>
                                    @foreach ($schools as $school)
                                    <option value="{{ $school->id }}">{{ $school->school_name }} - {{ $school->address }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('current_address') }} <span class="text-danger">*</span></label>
                                {!! Form::textarea('current_address', null, ['required', 'placeholder' => __('current_address'), 'class' => 'form-control', 'rows' => 3]) !!}
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('permanent_address') }} <span class="text-danger">*</span></label>
                                {!! Form::textarea('permanent_address', null, ['required', 'placeholder' => __('permanent_address'), 'class' => 'form-control', 'rows' => 3]) !!}
                            </div>
                        </div>
<!--                        <div class="input-group mb-3">-->
<!--                            <div class="input-group-prepend">-->
<!--                                <div class="input-group-text">-->
<!--                                    <input type="checkbox" name="grant_permission" aria-label="Checkbox for following text input" id="gridCheck">-->
<!--                                </div>-->
<!--                            </div>-->
<!--                            <label class="form-control" for="gridCheck">-->
<!--                                {{__('grant_permission_to_manage_students_parents')}}-->
<!--                            </label>-->
<!--                        </div>-->
<!--                        <div class="form-group text-info" style="font-size: 0.8rem;margin-top: -0.3rem">{{__('note_for_permission_of_student_manage')}}</div>-->
                        <input class="btn bight-pink" type="submit" value={{ __('submit') }}>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('principals') . ' ' . __('list') }}
                    </h4>
                    <div class="row">
                        <div class="col-9"></div>
                        <div class="col-3">
                            <button onclick="exportData('principals',null)" class="btn btn-success float-right"><i
                                        class="fa fa-file-excel-o"></i>&nbsp;Export
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                   data-url="{{ url('principals') }}" data-click-to-select="true"
                                   data-side-pagination="server" data-pagination="true"
                                   data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                   data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                   data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                                   data-mobile-responsive="true" data-sort-name="id"
                                   data-maintain-selected="true" data-export-types='["txt","excel"]'
                                   data-export-options='{ "fileName": "teacher-list-<?= date('d-m-y') ?>" ,"ignoreColumn":
                                    ["operate"]}'
                                   data-query-params="teacherQueryParams">
                                <thead>
                                <tr>
<!--                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">-->
<!--                                        {{ __('id') }}</th>-->
                                    <th scope="col" data-field="school_id" data-sortable="true" data-visible="false">
                                        {{ __('school_id') }}</th>
<!--                                    <th scope="col" data-field="id" data-sortable="false">{{ __('no.') }}-->
<!--                                    </th>-->
                                    <th scope="col" data-field="user_id" data-sortable="false">{{ __('user_id') }}</th>
                                    <th scope="col" data-field="first_name" data-sortable="false">
                                        {{ __('first_name') }}</th>
                                    <th scope="col" data-field="last_name" data-sortable="false">
                                        {{ __('last_name') }}</th>
                                    <th scope="col" data-field="gender" data-sortable="false">
                                        {{ __('gender') }}</th>
                                    <th scope="col" data-field="school_name" data-sortable="false">
                                        {{ __('school') }}</th>
                                    <th scope="col" data-field="email" data-sortable="false">
                                        {{ __('email') }}</th>
                                    <th scope="col" data-field="mobile" data-sortable="false">
                                        {{ __('mobile') }}</th>
                                    <th scope="col" data-field="dob" data-sortable="false">
                                        {{ __('dob') }}</th>
                                        <th data-events="teacherActionEvents" scope="col" data-field="operate"
                                        data-sortable="false">Unlink</th>

                                    <th scope="col" data-field="delete"
                                        data-sortable="false">Delete</th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="editModal" data-backdrop="static" tabindex="-1" role="dialog"
     aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ __('edit_principal') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <form id="editdata" class="editform" action="{{ url('principals') }}" novalidate="novalidate"
                  enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id">
                    <input type="hidden" name="id" id="id">
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('first_name') }} <span class="text-danger">*</span></label>
                            {!! Form::text('first_name', null, ['required', 'placeholder' => __('first_name'), 'class' => 'form-control', 'id' => 'first_name']) !!}

                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('last_name') }} <span class="text-danger">*</span></label>
                            {!! Form::text('last_name', null, ['required', 'placeholder' => __('last_name'), 'class' => 'form-control', 'id' => 'last_name']) !!}
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('gender') }} <span class="text-danger">*</span></label>
                            <div class="d-flex">
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        {!! Form::radio('gender', 'male', null, ['class' => 'form-check-input edit', 'id' => 'gender']) !!}
                                        {{ __('male') }}
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        {!! Form::radio('gender', 'female', null, ['class' => 'form-check-input edit', 'id' => 'gender']) !!}
                                        {{ __('female') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('email') }} <span class="text-danger">*</span></label>
                            {!! Form::text('email', null, ['required', 'placeholder' => __('email'), 'class' => 'form-control', 'id' => 'email' ,'readonly' => true]) !!}
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('mobile') }} <span class="text-danger">*</span></label>
                            {!! Form::number('mobile', null, ['required', 'min' => 1 , 'placeholder' => __('mobile'), 'class' => 'form-control', 'id' => 'mobile']) !!}

                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('image') }}</label><br>
                            {{-- <input type="file" name="image" id="edit_image" class="form-control" placeholder="{{__('image')}}"> --}}
                            <input type="file" name="image" class="file-upload-default" accept="image/png,image/jpeg,image/jpg" />
                            <div class="input-group col-xs-12">
                                <input type="text" class="form-control file-upload-info" disabled=""
                                       placeholder="{{ __('image') }}" />
                                <span class="input-group-append">
                                        <button class="file-upload-browse btn btn-theme"
                                                type="button">{{ __('upload') }}</button>
                                    </span>
                            </div>
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('dob') }} <span class="text-danger">*</span></label>
                            {!! Form::text('dob', null, ['required', 'placeholder' => __('dob'), 'class' => 'datepicker-popup-no-future form-control', 'id' => 'dob']) !!}
                            <span class="input-group-addon input-group-append">
                                </span>
                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('qualification') }} <span class="text-danger">*</span></label>
                            {!! Form::textarea('qualification', null, ['required', 'placeholder' => __('qualification'), 'class' => 'form-control', 'rows' => 3, 'id' => 'qualification']) !!}
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('schools') }} <span class="text-danger">*</span></label>
                            <select name="school_id" id="school_id" class="form-control select2">
                                <option value="">{{ __('select') . ' '. __('school') }}</option>
                                @foreach ($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->school_name }} - {{ $school->address }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('current_address') }} <span class="text-danger">*</span></label>
                            {!! Form::textarea('current_address', null, ['required', 'placeholder' => __('current_address'), 'class' => 'form-control', 'rows' => 3, 'id' => 'current_address']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('permanent_address') }} <span class="text-danger">*</span></label>
                            {!! Form::textarea('permanent_address', null, ['required', 'placeholder' => __('permanent_address'), 'class' => 'form-control', 'rows' => 3, 'id' => 'permanent_address']) !!}
                        </div>
                    </div>

<!--                    <div class="input-group mb-3">-->
<!--                        <div class="input-group-prepend">-->
<!--                            <div class="input-group-text">-->
<!--                                <input type="checkbox" name="edit_grant_permission" aria-label="Checkbox for following text input" id="edit_permission_chk" class="edit_permission_chk">-->
<!--                            </div>-->
<!--                        </div>-->
<!--                        <label class="form-control" for="edit_permission_chk">-->
<!--                            {{__('grant_permission_to_manage_students_parents')}}-->
<!--                        </label>-->
<!--                    </div>-->
<!--                    <div class="form-group text-info" style="font-size: 0.8rem;margin-top: -0.3rem">{{__('note_for_permission_of_student_manage')}}</div>-->
                </div>
                <div class="modal-footer">
                    <input class="btn btn-theme" type="submit" value={{ __('submit') }}>
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ __('cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
