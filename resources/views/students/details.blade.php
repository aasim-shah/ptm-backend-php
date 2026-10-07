<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
@extends('layouts.master')

@section('title')
{{ __('students') }}
@endsection

@section('content')
<style>
    .hidden {
        display: none;
    }
</style>
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage'). ' '.__('students') }}
        </h3>
        <button onclick="exportData('students',null)" class="btn btn-success"><i class="fa fa-file-excel-o"></i>&nbsp;Export</button>

    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('students'). ' '.__('list') }}
                    </h4>
                    <div id="toolbar">
                        <div class="row">
                            @if(isset($schools))

                            <div class="col">
                                <select onchange="selectSchool(this.value)" name="school_id" id="school_id"
                                        class="form-control select2">
                                    <option value="">{{ __('select'). ' '.__('school') }}</option>
                                    @foreach ($schools as $school)
                                    <option value="{{ $school->id }}">{{ $school->school_name }} - {{ $school->address
                                        }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @endIf

                            <div class="col">
                                <select name="filter_class_section_id" id="filter_class_section_id"
                                        class="form-control">
                                    <option value="">{{ __('select_class_section') }}</option>
                                    @foreach ($class_section as $class)
                                    <option value={{ $class->id }}>
                                        {{ $class->class->name. ' '.$class->section->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">

                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table table-responsive' id='table_list'
                                   data-toggle="table" data-url="{{ url('students-list') }}" data-click-to-select="true"
                                   data-side-pagination="server" data-pagination="true"
                                   data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                   data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                                   data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1"
                                   data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                                   data-sort-order="asc" data-maintain-selected="true"
                                   data-export-types='["txt","excel"]'
                                   data-export-options='{ "fileName": "students-list-<?= date('d-m-y') ?>" ,"ignoreColumn":
                                    ["operate"]}' data-query-params="studentDetailsqueryParams"
                                   data-check-on-init="true">

                                <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}
                                    </th>
                                    <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
<!--                                    <th scope="col" data-field="user_id" data-sortable="false"-->
<!--                                        data-visible="false">{{ __('user_id') }}-->
<!--                                    </th>-->
                                    <th scope="col" data-field="first_name" data-sortable="false">
                                        {{ __('first_name') }}
                                    </th>
                                    <th scope="col" data-field="last_name" data-sortable="false">
                                        {{ __('last_name') }}
                                    </th>
                                    <th scope="col" data-field="dob" data-sortable="false">{{ __('dob') }}
                                    </th>
                                    <th scope="col" data-field="image" data-sortable="false"
                                        data-formatter="imageFormatter">{{ __('image') }}
                                    </th>
<!--                                    <th scope="col" data-field="class_section_id" data-sortable="false"-->
<!--                                        data-visible="false">-->
<!--                                        {{ __('class'). ' '.__('section'). ' '.__('id') }}-->
<!--                                    </th>-->
                                    <th scope="col" data-field="class_section_name" data-sortable="false">
                                        {{ __('class'). ' '.__('section') }}
                                    </th>
<!--                                    <th scope="col" data-field="category_id" data-sortable="false"-->
<!--                                        data-visible="false">{{ __('category'). ' '.__('id') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="category_name" data-sortable="false">-->
<!--                                        {{ __('category') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="admission_no" data-sortable="false">-->
<!--                                        {{ __('gr_number') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="roll_number" data-sortable="false">-->
<!--                                        {{ __('roll_no') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="caste" data-sortable="false">{{ __('caste') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="religion" data-sortable="false">-->
<!--                                        {{ __('religion') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="admission_date" data-sortable="false">-->
<!--                                        {{ __('admission_date') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="blood_group" data-sortable="false">-->
<!--                                        {{ __('blood_group') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="height" data-sortable="false">-->
<!--                                        {{ __('height') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="weight" data-sortable="false">-->
<!--                                        {{ __('weight') }}-->
<!--                                    </th>-->
                                    <th scope="col" data-field="father_first_name" data-sortable="false">
                                        {{ __('father'). ' '.__('name') }}
                                    </th>
<!--                                    <th scope="col" data-field="father_mobile" data-sortable="false">-->
<!--                                        {{ __('father'). ' '.__('mobile') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="father_occupation" data-sortable="false">-->
<!--                                        {{ __('father'). ' '.__('occupation') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="father_image" data-sortable="false"-->
<!--                                        data-formatter="fatherImageFormatter">-->
<!--                                        {{ __('father'). ' '.__('image') }}-->
<!--                                    </th>-->
                                    <th scope="col" data-field="mother_first_name" data-sortable="false">
                                        {{ __('mother'). ' '.__('name') }}
                                    </th>
<!--                                    <th scope="col" data-field="mother_occupation" data-sortable="false">-->
<!--                                        {{ __('parents'). ' '.__('occupation') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="mother_image" data-sortable="false"-->
<!--                                        data-formatter="motherImageFormatter">-->
<!--                                        {{ __('mother'). ' '.__('image') }}-->
<!--                                    </th>-->
<!--                                    <th scope="col" data-field="is_new_admission" data-sortable="false"-->
<!--                                        data-visible="false">{{ __('is_new_admission') }}-->
<!--                                    </th>-->
                                    @canany(['student-edit', 'student-delete'])
                                    <th data-events="studentEvents" data-width="150" scope="col" data-field="operate"
                                        data-sortable="false">{{ __('action') }}
                                    </th>
                                    @endcanany
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

@can('student-edit')
<div class="modal fade" id="editModal" data-backdrop="static" tabindex="-1" role="dialog"
     aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel">{{ __('edit'). ' '.__('students') }}</h4><br>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <form id="edit-form" class="edit-student-registration-form" novalidate="novalidate"
                  action="{{ url('students') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">

                    <div class="row">
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('first_name') }} <span class="text-danger">*</span></label>
                            {!! Form::text('first_name', null, ['placeholder' => __('first_name'), 'class' =>
                            'form-control', 'id' => 'edit_first_name']) !!}

                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('last_name') }} <span class="text-danger">*</span></label>
                            {!! Form::text('last_name', null, ['placeholder' => __('last_name'), 'class' =>
                            'form-control', 'id' => 'edit_last_name']) !!}

                        </div>
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('mobile') }}</label>-->
<!--                            {!! Form::tel('mobile', null, ['placeholder' => __('mobile'), 'class' => 'form-control', 'id' => 'edit_mobile' ]) !!}-->
<!--                        </div>-->
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('gender') }} <span class="text-danger">*</span></label><br>
                            <div class="d-flex">
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        {!! Form::radio('gender', 'male', ['id' => 'male']) !!}
                                        {{ __('male') }}
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        {!! Form::radio('gender', 'female', ['id' => 'female']) !!}
                                        {{ __('female') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('image') }} </label>
                            <input type="file" name="image" class="file-upload-default"/>
                            <div class="input-group col-xs-12">
                                <input type="text" class="form-control file-upload-info" disabled=""
                                       placeholder="{{ __('image') }}" required="required" id="edit_image"/>
                                <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme"
                                                    type="button">{{ __('upload') }}</button>
                                        </span>
                            </div>

                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('dob') }} <span class="text-danger">*</span></label>
                            {!! Form::text('dob', null, ['placeholder' => __('dob'), 'class' =>
                            'datepicker-popup-no-future form-control', 'id' => 'edit_dob']) !!}
                            <span class="input-group-addon input-group-append">
                                    </span>
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('class'). ' '.__('section') }} <span
                                        class="text-danger">*</span></label>
                            <select required name="class_section_id" class="form-control" id="edit_class_section_id">
                                <option value="">{{ __('select'). ' '.__('class'). ' '.__('section') }}
                                </option>
                                @foreach ($class_section as $section)
                                <option value="{{ $section->id }}">{{ $section->class->name }} -
                                    {{ $section->section->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label> {{ __('category') }} <span class="text-danger">*</span></label>-->
<!--                            <select name="category_id" class="form-control" id="edit_category_id">-->
<!--                                <option value="">{{ __('select'). ' '.__('category') }}</option>-->
<!--                                @foreach ($category as $cat)-->
<!--                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>-->
<!--                                @endforeach-->
<!--                            </select>-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('gr_number') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::text('admission_no', null, ['placeholder' => __('admission_no'), 'class' => 'form-control', 'id' => 'edit_admission_no' ,'readonly'=>true]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('roll_no') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::text('roll_number', null, ['placeholder' => __('roll_no'), 'class' => 'form-control', 'id' => 'edit_roll_number']) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('caste') }}</label>-->
<!--                            {!! Form::text('caste', null, ['placeholder' => __('caste'), 'class' => 'form-control', 'id' => 'edit_caste']) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('religion') }}</label>-->
<!--                            {!! Form::text('religion', null, ['placeholder' => __('religion'), 'class' => 'form-control', 'id' => 'edit_religion']) !!}-->
<!--                        </div>-->
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('admission_date') }} <span class="text-danger">*</span></label>
                            {!! Form::text('admission_date', null, ['placeholder' => __('admission_date'), 'class' =>
                            'datepicker-popup-no-future form-control', 'id' => 'edit_admission_date']) !!}
                            <span class="input-group-addon input-group-append">
                                    </span>
                        </div>

<!--                        <div class="form-group col-12">-->
<!--                            <label>{{ __('address') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::textarea('current_address', null, ['placeholder' => __('current_address'), 'class' => 'form-control', 'id' => 'current_address', 'id' => 'edit_current_address', 'rows' => 2]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-12">-->
<!--                            <label>{{ __('permanent_address') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::textarea('permanent_address', null, ['placeholder' => __('permanent_address'), 'class' => 'form-control', 'id' => 'permanent_address', 'id' => 'edit_permanent_address', 'rows' => 2]) !!}-->
<!--                        </div>-->
                    </div>
                    <hr>
                    <h4>Parent Guardian Details</h4><br>
                    <div class="row">
<!--                        <div class="form-group col-sm-12 col-md-12">-->
<!--                            <label>{{ __('father_email') }} </label>-->
<!--                            <select class="edit-father-search w-100" id="edit_father_email"-->
<!--                                    name="father_email"></select>-->
<!--                        </div>-->

                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('father'). ' '.__('first_name') }} </label>
                            {!! Form::text('father_first_name', null, ['placeholder' => __('father') . ' ' .
                            __('first_name'), 'class' => 'form-control', 'id' => 'edit_father_first_name']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('father'). ' '.__('last_name') }} </label>
                            {!! Form::text('father_last_name', null, ['placeholder' => __('father') . ' ' .
                            __('last_name'), 'class' => 'form-control', 'id' => 'edit_father_last_name']) !!}
                        </div>
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('father'). ' '.__('mobile') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            {!! Form::text('father_mobile', null, ['placeholder' => __('father') . ' ' . __('mobile'), 'class' => 'form-control', 'id' => 'edit_father_mobile', 'min' => 1]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('father'). ' '.__('dob') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::text('father_dob', null, ['placeholder' => __('father') . ' ' . __('dob'), 'class' => 'form-control datepicker-popup-no-future form-control', 'id' => 'edit_father_dob', 'readonly' => true]) !!}-->
<!--                        </div>-->

<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('father'). ' '.__('occupation') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            {!! Form::text('father_occupation', null, ['placeholder' => __('father') . ' ' . __('occupation'), 'class' => 'form-control', 'id' => 'edit_father_occupation', 'readonly' => true]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('father'). ' '.__('image') }} <span class="text-danger">*</span></label>-->
<!--                            <input type="file" name="father_image" class="father_image file-upload-default"/>-->
<!--                            <div class="input-group col-xs-12">-->
<!--                                <input type="text" class="form-control file-upload-info" disabled=""-->
<!--                                       placeholder="{{ __('father') . ' ' . __('image') }}"/>-->
<!--                                <span class="input-group-append">-->
<!--                                            <button class="file-upload-browse btn btn-theme"-->
<!--                                                    type="button">{{ __('upload') }}</button>-->
<!--                                        </span>-->
<!--                            </div>-->
<!--                            <div style="width: 100px;">-->
<!--                                <img src="" id="edit-father-image-tag" class="img-fluid w-100"/>-->
<!--                            </div>-->
<!--                        </div>-->
                    </div>

                    <div class="row">
<!--                        <div class="form-group col-sm-12 col-md-12">-->
<!--                            <label>{{ __('mother_email') }} <span class="text-danger">*</span></label>-->
<!--                            <select class="edit-mother-search w-100" id="edit_mother_email"-->
<!--                                    name="mother_email"></select>-->
<!--                        </div>-->

                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('mother'). ' '.__('first_name') }}</label>
                            {!! Form::text('mother_first_name', null, ['placeholder' => __('mother') . ' ' .
                            __('first_name'), 'class' => 'form-control', 'id' => 'edit_mother_first_name']) !!}
                        </div>

                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('mother'). ' '.__('last_name') }}</label>
                            {!! Form::text('mother_last_name', null, ['placeholder' => __('mother') . ' ' .
                            __('last_name'), 'class' => 'form-control', 'id' => 'edit_mother_last_name']) !!}
                        </div>
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('mother'). ' '.__('mobile') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            {!! Form::text('mother_mobile', null, ['placeholder' => __('mother') . ' ' . __('mobile'), 'class' => 'form-control', 'id' => 'edit_mother_mobile', 'min' => 1]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('mother'). ' '.__('dob') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::text('mother_dob', null, ['placeholder' => __('mother') . ' ' . __('dob'), 'class' => 'form-control datepicker-popup-no-future form-control', 'id' => 'edit_mother_dob', 'readonly' => true]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('mother'). ' '.__('occupation') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            {!! Form::text('mother_occupation', null, ['placeholder' => __('mother') . ' ' . __('occupation'), 'class' => 'form-control', 'id' => 'edit_mother_occupation', 'readonly' => true]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('mother'). ' '.__('image') }} <span class="text-danger">*</span></label>-->
<!--                            <input type="file" name="mother_image" class="file-upload-default"/>-->
<!--                            <div class="input-group col-xs-12">-->
<!--                                <input type="text" class="form-control file-upload-info" disabled=""-->
<!--                                       placeholder="{{ __('mother') . ' ' . __('image') }}"/>-->
<!--                                <span class="input-group-append">-->
<!--                                            <button class="file-upload-browse btn btn-theme"-->
<!--                                                    type="button">{{ __('upload') }}</button>-->
<!--                                        </span>-->
<!--                            </div>-->
<!--                            <div style="width: 100px;">-->
<!--                                <img src="" id="edit-mother-image-tag" class="img-fluid w-100"/>-->
<!--                            </div>-->
<!--                        </div>-->
                    </div>

                    <div class="form-group hidden">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input"
                                       id="show-edit-guardian-details">{{ __('guardian_details') }}
                            </label>
                        </div>
                    </div>
                    <div class="row" id="edit_guardian_div" style="display:none;">
<!--                        <div class="form-group col-sm-12 col-md-12">-->
<!--                            <label>{{ __('guardian'). ' '.__('email') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            <select class="edit-guardian-search form-control" id="edit_guardian_email"-->
<!--                                    name="guardian_email"></select>-->
<!--                        </div>-->

                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('guardian'). ' '.__('first_name') }} <span
                                        class="text-danger">*</span></label>
                            {!! Form::text('guardian_first_name', null, ['placeholder' => __('guardian') . ' ' .
                            __('first_name'), 'class' => 'form-control', 'id' => 'edit_guardian_first_name', 'readonly'
                            => true]) !!}
                        </div>

                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('guardian'). ' '.__('last_name') }} <span
                                        class="text-danger">*</span></label>
                            {!! Form::text('guardian_last_name', null, ['placeholder' => __('guardian') . ' ' .
                            __('last_name'), 'class' => 'form-control', 'id' => 'edit_guardian_last_name', 'readonly' =>
                            true]) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('guardian'). ' '.__('mobile') }} <span
                                        class="text-danger">*</span></label>
                            {!! Form::text('guardian_mobile', null, ['placeholder' => __('guardian') . ' ' .
                            __('mobile'), 'class' => 'form-control', 'id' => 'edit_guardian_mobile', 'readonly' => true
                            , 'min' => 1]) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('gender') }} <span class="text-danger">*</span></label><br>
                            <div class="d-flex">
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input type="radio" name="guardian_gender" value="male" id="edit_guardian_male">
                                        {{ __('male') }}
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input type="radio" name="guardian_gender" value="female"
                                               id="edit_guardian_female">
                                        {{ __('female') }}
                                    </label>
                                </div>
                            </div>
                        </div>
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('guardian'). ' '.__('dob') }} <span class="text-danger">*</span></label>-->
<!--                            {!! Form::text('guardian_dob', null, ['placeholder' => __('guardian') . ' ' . __('dob'), 'class' => 'form-control datepicker-popup-no-future form-control', 'id' => 'edit_guardian_dob', 'readonly' => true]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('guardian'). ' '.__('occupation') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            {!! Form::text('guardian_occupation', null, ['placeholder' => __('guardian') . ' ' . __('occupation'), 'class' => 'form-control', 'id' => 'edit_guardian_occupation', 'readonly' => true]) !!}-->
<!--                        </div>-->
<!--                        <div class="form-group col-sm-12 col-md-4">-->
<!--                            <label>{{ __('guardian'). ' '.__('image') }} <span-->
<!--                                        class="text-danger">*</span></label>-->
<!--                            <input type="file" name="guardian_image" class="file-upload-default"/>-->
<!--                            <div class="input-group col-xs-12">-->
<!--                                <input type="text" class="form-control file-upload-info" disabled=""-->
<!--                                       placeholder="{{ __('guardian') . ' ' . __('image') }}"/>-->
<!--                                <span class="input-group-append">-->
<!--                                            <button class="file-upload-browse btn btn-theme"-->
<!--                                                    type="button">{{ __('upload') }}</button>-->
<!--                                        </span>-->
<!--                            </div>-->
<!--                            <div style="width: 100px;">-->
<!--                                <img src="" id="edit-guardian-image-tag" class="img-fluid w-100"/>-->
<!--                            </div>-->
<!--                        </div>-->
                    </div>
                </div>
                <div class="modal-footer">
                    <input class="btn btn-theme" type="submit" value={{ __('submit') }}>
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ __('cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection

<script>
    function selectSchool(id) {
        var classSectionSelect = $('#filter_class_section_id');

        if (id !== '' && id !== 'undefined') {
            let url = "{{ route('class-section-school', ':school_id') }}";
            url = url.replace(':school_id', id);
            classSectionSelect.find('option').remove();
            $.ajax({
                url: url,
                type: "GET",
                success: function (data) {
                    classSectionSelect.append("<option selected value=''>Select Class</option>");
                    for (let i = 0; i < data.length; i++) {
                        let class_section_id = data[i].id;
                        classSectionSelect.append("<option value=" + class_section_id + " > " + data[i].class.name + " " + data[i].section.name + "</option>");
                    }
                },
                error: function (error) {
                    console.log(`Error ${error}`);
                }
            });
        } else {
            classSectionSelect.find('option').remove();
        }
    }
</script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<script>
    $(document).ready(function () {
        if ($('#edit_father_first_name').attr('placeholder') || $('#edit_father_last_name').attr('placeholder')){
            $('#edit_father_first_name').closest('.form-group').hide();
            $('#edit_father_last_name').closest('.form-group').hide();

        } else if ($('#edit_mother_first_name').attr('placeholder') || $('#edit_mother_last_name').attr('placeholder')) {
            $('#edit_mother_first_name').closest('.form-group').hide();
            $('#edit_mother_last_name').closest('.form-group').hide();
        }
    });
</script>