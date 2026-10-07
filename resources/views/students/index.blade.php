@extends('layouts.master')

@section('title')
    {{ __('students') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage') . ' ' . __('students') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('create') . ' ' . __('students') }}
                        </h4>
                        <form class="pt-3 student-registration-form" enctype="multipart/form-data" action="{{ route('students.store') }}" method="POST" novalidate="novalidate">
                            @csrf
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('first_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('first_name', null, ['placeholder' => __('first_name'), 'class' => 'form-control']) !!}

                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('last_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('last_name', null, ['placeholder' => __('last_name'), 'class' => 'form-control']) !!}

                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mobile') }}</label>
                                    {!! Form::number('mobile', null, ['placeholder' => __('mobile'), 'class' => 'form-control' , 'min' => 0]) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
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
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('image') }} <span class="text-danger">*</span></label>
                                    <input type="file" name="image" class="file-upload-default"/>
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('image') }}" required="required"/>
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('dob') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('dob', null, ['placeholder' => __('dob'), 'class' => 'datepicker-popup-no-future form-control']) !!}
                                    <span class="input-group-addon input-group-append">
                                    </span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('class') . ' ' . __('section') }} <span class="text-danger">*</span></label>
                                    <select name="class_section_id" id="class_section" class="form-control select2">
                                        <option value="">{{ __('select') . ' ' . __('class') . ' ' . __('section') }}</option>
                                        @foreach ($class_section as $section)
                                            <option value="{{ $section->id }}">{{ $section->class->name }} - {{ $section->section->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label> {{ __('category') }} <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-control">
                                        <option value="">{{ __('select') . ' ' . __('category') }}</option>
                                        @foreach ($category as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('gr_number') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('admission_no', $admission_no, ['readonly','placeholder' => __('gr_number'), 'class' => 'form-control']) !!}
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('caste') }}</label>
                                    {!! Form::text('caste', null, ['placeholder' => __('caste'), 'class' => 'form-control']) !!}

                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('religion') }}</label>
                                    {!! Form::text('religion', null, ['placeholder' => __('religion'), 'class' => 'form-control']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('admission_date') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('admission_date', null, ['placeholder' => __('admission_date'), 'class' => 'datepicker-popup-no-future form-control']) !!}
                                    <span class="input-group-addon input-group-append">
                                    </span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('blood_group') }} <span class="text-danger">*</span></label>
                                    <select name="blood_group" class="form-control">
                                        <option value="">{{ __('select') . ' ' . __('blood_group') }}</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('height') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('height', null, ['placeholder' => __('height'), 'class' => 'form-control']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('weight') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('weight', null, ['placeholder' => __('weight'), 'class' => 'form-control']) !!}
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-12">
                                    <label>{{ __('current_address') }} <span class="text-danger">*</span></label>
                                    {!! Form::textarea('current_address', null, ['placeholder' => __('current_address'), 'class' => 'form-control', 'id' => 'current_address','rows'=>2]) !!}
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-12">
                                    <label>{{ __('permanent_address') }} <span class="text-danger">*</span></label>
                                    {!! Form::textarea('permanent_address', null, ['placeholder' => __('permanent_address'), 'class' => 'form-control', 'id' => 'permanent_address','rows'=>2]) !!}
                                </div>
                            </div>
                            <hr>
                            <h4>{{ __('parent_guardian_details') }}</h4><br>
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-12">
                                    <label>{{ __('father_email') }} <span class="text-danger">*</span></label>
                                    @if(config('environment.DEMO_MODE'))
                                        <select class="father-search w-100" id="father_email" name="father_email" disabled></select>
                                    @else
                                        <select class="father-search w-100" id="father_email" name="father_email"></select>
                                    @endif
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('father') . ' ' . __('first_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('father_first_name', null, ['placeholder' => __('father') . ' ' . __('first_name'), 'class' => 'form-control', 'id' => 'father_first_name']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('father') . ' ' . __('last_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('father_last_name', null, ['placeholder' => __('father') . ' ' . __('last_name'), 'class' => 'form-control', 'id' => 'father_last_name']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('father') . ' ' . __('mobile') }} <span class="text-danger">*</span></label>
                                    {!! Form::number('father_mobile', null, ['placeholder' => __('father') . ' ' . __('mobile'), 'class' => 'form-control', 'id' => 'father_mobile', 'min' => 0]) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('father') . ' ' . __('dob') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('father_dob', null, ['placeholder' => __('father') . ' ' . __('dob'), 'class' => 'form-control datepicker-popup-no-future form-control', 'id' => 'father_dob']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('father') . ' ' . __('occupation') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('father_occupation', null, ['placeholder' => __('father') . ' ' . __('occupation'), 'class' => 'form-control', 'id' => 'father_occupation']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('father') . ' ' . __('image') }} <span class="text-danger">*</span></label>
                                    <input type="file" name="father_image" class="father_image file-upload-default"/>
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('father') . ' ' . __('image') }}"/>
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                        </span>
                                    </div>
                                    <div style="width: 100px;">
                                        <img src="" id="father-image-tag" class="img-fluid w-100"/>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-12">
                                    <label>{{ __('mother_email') }} <span class="text-danger">*</span></label>
                                    @if(config('environment.DEMO_MODE'))
                                        <select class="mother-search w-100" id="mother_email" name="mother_email" disabled></select>
                                    @else
                                        <select class="mother-search w-100" id="mother_email" name="mother_email"></select>
                                    @endif

                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mother') . ' ' . __('first_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('mother_first_name', null, ['placeholder' => __('mother') . ' ' . __('first_name'), 'class' => 'form-control', 'id' => 'mother_first_name']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mother') . ' ' . __('last_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('mother_last_name', null, ['placeholder' => __('mother') . ' ' . __('last_name'), 'class' => 'form-control', 'id' => 'mother_last_name']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mother') . ' ' . __('mobile') }} <span class="text-danger">*</span></label>
                                    {!! Form::number('mother_mobile', null, ['placeholder' => __('mother') . ' ' . __('mobile'), 'class' => 'form-control', 'id' => 'mother_mobile', 'min' => 0]) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mother') . ' ' . __('dob') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('mother_dob', null, ['placeholder' => __('mother') . ' ' . __('dob'), 'class' => 'form-control datepicker-popup-no-future form-control', 'id' => 'mother_dob']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mother') . ' ' . __('occupation') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('mother_occupation', null, ['placeholder' => __('mother') . ' ' . __('occupation'), 'class' => 'form-control', 'id' => 'mother_occupation']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('mother') . ' ' . __('image') }} <span class="text-danger">*</span></label>
                                    <input type="file" name="mother_image" class="mother_image file-upload-default"/>
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('mother') . ' ' . __('image') }}"/>
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                        </span>
                                    </div>
                                    <div style="width: 100px;">
                                        <img src="" id="mother-image-tag" class="img-fluid w-100"/>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="form-group">
                                <div class="form-check">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" id="show-guardian-details">{{ __('guardian_details') }}
                                    </label>
                                </div>
                            </div>
                            <div class="row" id="guardian_div" style="display:none;">
                                <div class="form-group col-sm-12 col-md-12">
                                    <label>{{ __('guardian') . ' ' . __('email') }} <span class="text-danger">*</span></label>
                                    @if(config('environment.DEMO_MODE'))
                                        <select class="guardian-search form-control" id="guardian_email" name="guardian_email" disabled></select>
                                    @else
                                        <select class="guardian-search form-control" id="guardian_email" name="guardian_email"></select>
                                    @endif
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('guardian') . ' ' . __('first_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('guardian_first_name', null, ['placeholder' => __('guardian') . ' ' . __('first_name'), 'class' => 'form-control', 'id' => 'guardian_first_name']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('guardian') . ' ' . __('last_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('guardian_last_name', null, ['placeholder' => __('guardian') . ' ' . __('last_name'), 'class' => 'form-control', 'id' => 'guardian_last_name']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('guardian') . ' ' . __('mobile') }} <span class="text-danger">*</span></label>
                                    {!! Form::number('guardian_mobile', null, ['placeholder' => __('guardian') . ' ' . __('mobile'), 'class' => 'form-control', 'id' => 'guardian_mobile' , 'min' => 0]) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-12">
                                    <label>{{ __('gender') }} <span class="text-danger">*</span></label><br>
                                    <div class="d-flex">
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">
                                                <input type="radio" name="guardian_gender" value="male" id="guardian_male">
                                                {{ __('male') }}
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">
                                                <input type="radio" name="guardian_gender" value="female" id="guardian_female">
                                                {{ __('female') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('guardian') . ' ' . __('dob') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('guardian_dob', null, ['placeholder' => __('guardian') . ' ' . __('dob'), 'class' => 'form-control datepicker-popup-no-future form-control', 'id' => 'guardian_dob']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('guardian') . ' ' . __('occupation') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('guardian_occupation', null, ['placeholder' => __('guardian') . ' ' . __('occupation'), 'class' => 'form-control', 'id' => 'guardian_occupation']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('guardian') . ' ' . __('image') }} <span class="text-danger">*</span></label>
                                    <input type="file" name="guardian_image" class="guardian_image file-upload-default"/>
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('guardian') . ' ' . __('image') }}"/>
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                        </span>
                                    </div>
                                    <div style="width: 100px;">
                                        <img src="" id="guardian-image-tag" class="img-fluid w-100"/>
                                    </div>
                                </div>
                            </div>
                            <input class="btn btn-theme" type="submit" value={{ __('submit') }}>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
