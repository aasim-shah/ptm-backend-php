@extends('layouts.master')

@section('title')
{{ __('schools') }}
@endsection

@section('content')
<style>
    .error {
        color: red;
    }
</style>
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage'). ' '.__('school') }}
        </h3>

    </div>

    <div class="row">
        @hasrole(['Super Admin'])

        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('create'). ' '.__('school') }}
                    </h4>
                    <form id="edit-form" class="add-school-form" novalidate="novalidate" method="PUT"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <input type="hidden" name="edit_id" id="edit_id">

                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>Country <span class="text-danger">*</span></label>
                                    <select id="country-picker" name="country" class="form-control" required>

                                        <option value="">{{ __('select'). ' '.__('country') }}</option>
                                        <option style="background-image:url('images/uk-flag.png')" value="230"> UK
                                        </option>
                                        <option style="background-image:url('images/us-flag.png')" value="231"> USA
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('school_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('school_name', null, ['required','placeholder' => __('school_name'),
                                    'class' =>
                                    'form-control', 'id' => 'school_name']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    @if(isset($principals))
                                    <label>Principal</label>
                                    <select name="principal_id" id="principal_id"
                                            class="form-control">
                                        <option value="">{{ __('select'). ' '.__('principal') }}</option>
                                        @foreach ($principals as $principal)
                                        <option value="{{ $principal->id }}">{{ $principal->first_name }}
                                            {{ $principal->last_name
                                            }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @endIf
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('Phone') }}</label>
                                    {!! Form::tel('phone', null, ['placeholder' => __('Phone'), 'class' =>
                                    'form-control',
                                    'id' => 'phone']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('Address') }}<span class="text-danger">*</span></label>
                                    {!! Form::text('address', null, ['required','placeholder' => __('Address'), 'class'
                                    =>
                                    'form-control',
                                    'id' => 'address']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('post_code') }}<span class="text-danger">*</span></label>
                                    {!! Form::text('post_code', null, ['required','placeholder' => __('post_code'),
                                    'class' =>
                                    'form-control',
                                    'id' => 'post_code' , 'min' => 1]) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>City Name</label>
                                    {!! Form::text('post_town', null, ['required','placeholder' => __('City Name'),
                                    'class' =>
                                    'form-control',
                                    'id' => 'post_town']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4 us-fields">
                                    <label>State</label>
                                    {!! Form::text('state', null, ['required','placeholder' => __('State'), 'class' =>
                                    'form-control',
                                    'id' => 'state']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('website') }}</label>
                                    {!! Form::text('website', null, ['placeholder' => __('website'), 'class' =>
                                    'form-control',
                                    'id' => 'website']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4 uk-fields">
                                    <label>{{ __('locality') }}</label>
                                    {!! Form::text('locality', null, ['required', 'placeholder' => __('locality'),
                                    'class' => 'form-control',
                                    'id' => 'locality']) !!}
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('email') }}<span class="text-danger">*</span></label>
                                    {!! Form::text('email', null, ['placeholder' => __('email'), 'class' =>
                                    'form-control',
                                    'id' => 'email']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('image') }} </label>
                                    <input type="file" name="image" id="school_image" class="file-upload-default"/>
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled=""
                                               placeholder="{{ __('image') }}" required="required"/>
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn theme-green"
                                                    type="button">{{ __('upload') }}</button>
                                        </span>
                                    </div>
                                </div>


                            </div>

                        </div>
                        <div class="modal-footer">
                            <input onclick="addSchool()" class="btn theme-green" type="button" value={{ __('submit') }}>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endrole

        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('schools_list') }}
                    </h4>
                    <div class="row">
                        <div class="col-9"></div>
                        <div class="col-3">
                            <button onclick="exportData('schools',null)" class="btn btn-success float-right"><i
                                        class="fa fa-file-excel-o"></i>&nbsp;Export
                            </button>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                   data-url="{{ url('schools-list') }}" data-click-to-select="true"
                                   data-side-pagination="server" data-pagination="true"
                                   data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                   data-toolbar="#toolbar"
                                   data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                   data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                                   data-mobile-responsive="true" data-sort-name="id" data-sort-order="asc"
                                   data-maintain-selected="true" data-export-types='["txt","excel"]'
                                   data-export-options='{ "fileName": "school-list-<?= date('d-m-y') ?>" ,"ignoreColumn":
                                    ["operate"]}'
                                   data-query-params="schoolsQueryParams">
                                <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}
                                    </th>
                                    <th scope="col" data-field="school_name" data-sortable="false">
                                        {{ __('school_name')}}
                                    </th>
                                    <th scope="col" data-field="address" data-sortable="false">{{ __('address') }}
                                    </th>
                                    <th scope="col" data-field="locality" data-sortable="false">
                                        {{ __('locality') }}
                                    </th>
                                    <th scope="col" data-field="website" data-sortable="false">
                                        {{ __('website') }}
                                    </th>
                                    <th scope="col" data-field="post_town" data-sortable="false">
                                        {{ __('post_town') }}
                                    </th>
                                    <th scope="col" data-field="post_code" data-sortable="false">
                                        {{ __('post_code') }}
                                    </th>
                                    <th scope="col" data-field="email" data-sortable="false">
                                        {{ __('email') }}
                                    </th>
                                    <th scope="col" data-field="phone" data-sortable="false">
                                        {{ __('phone') }}
                                    </th>
                                    <th scope="col" data-field="image"
                                        data-sortable="false" data-formatter="imageFormatter">{{ __('image') }}
                                    </th>

                                    <th data-events="schoolsActionEvents" scope="col" data-field="operate"
                                        data-sortable="false">{{ __('action') }}
                                    </th>
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
                <h5 class="modal-title" id="exampleModalLabel">{{ __('edit_school') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <form id="edit-form" class="edit-school-form" novalidate="novalidate"
                  action="{{ route('update-school') }}" enctype="multipart/form-data" method="PUT">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="edit_school_id" id="edit_school_id">

                    <div class="row">
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('school_name') }} <span class="text-danger">*</span></label>
                            {!! Form::text('school_name', null, ['placeholder' => __('school_name'), 'class' =>
                            'form-control', 'id' => 'edit_school_name']) !!}

                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('phone') }}</label>
                            {!! Form::tel('phone', null, ['placeholder' => __('phone'), 'class' => 'form-control',
                            'id' => 'edit_phone' , ]) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('post_code') }}<span class="text-danger">*</span></label>
                            {!! Form::text('post_code', null, ['placeholder' => __('post_code'), 'class' =>
                            'form-control',
                            'id' => 'edit_post_code' , ]) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('post_town') }} </label>
                            {!! Form::text('post_town', null, ['placeholder' => __('post_town'), 'class' =>
                            'form-control',
                            'id' => 'edit_post_town' , ]) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('website') }}</label>
                            {!! Form::text('website', null, ['placeholder' => __('website'), 'class' =>
                            'form-control',
                            'id' => 'edit_website']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('locality') }}</label>
                            {!! Form::text('locality', null, ['placeholder' => __('locality'), 'class' =>
                            'form-control',
                            'id' => 'edit_locality']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('email') }}<span class="text-danger">*</span></label>
                            {!! Form::text('email', null, ['placeholder' => __('email'), 'class' => 'form-control',
                            'id' => 'edit_email']) !!}
                        </div>

                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('image') }}</label>
                            <input type="file" name="image" class="file-upload-default"/>
                            <div class="input-group col-xs-12">
                                <input type="text" class="form-control file-upload-info" disabled=""
                                       placeholder="{{ __('image') }}"  id="edit_image"/>
                                <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme"
                                                    type="button">{{ __('upload') }}</button>
                                        </span>
                            </div>
                            <div style="width: 100px;">
                                <img src="" id="edit-student-image-tag" class="img-fluid w-100"/>
                            </div>
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('address') }} <span class="text-danger">*</span></label>
                            {!! Form::text('address', null, ['placeholder' => __('address'), 'class' =>
                            'form-control', 'id' => 'edit_address']) !!}
                            <span class="input-group-addon input-group-append">
                                    </span>
                        </div>

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

@endsection
@section('js')
<script>
    $(".us-fields").hide();
    $(".uk-fields").hide();

    // UK 230
    // US 231

    $("#country-picker").on('change', function () {
        let country_id = this.value;

        if (country_id === '231') {
            $(".uk-fields").hide()
            $(".us-fields").show()
        } else {
            $(".uk-fields").show()
            $(".us-fields").hide()
        }
    });

    function addSchool(){
        console.log("ADD SCHOOL");

        var formData = new FormData();

        let school_name = $("#school_name").val();
        let country = $("#country-picker").val();
        let email = $("#email").val();
        let website = $("#website").val();
        let principal_id = $("#principal_id").val();
        let address = $("#address").val();
        let post_code = $("#post_code").val();
        let state = $("#state").val();
        let post_town = $("#post_town").val();
        let phone = $("#phone").val();
        let locality = $("#locality").val();
        let imagefile = document.querySelector('#school_image');

        if (imagefile && imagefile.files && imagefile.files.length > 0) {
            formData.append("image", imagefile.files[0]);
        } else {
            formData.append("image", $('#school_image').val()); // or formData.append("image", "");
        }
        formData.append("school_name", school_name);
        formData.append("country", country);
        formData.append("email", email);
        formData.append("website", website);
        formData.append("principal_id", principal_id);
        formData.append("address", address);
        formData.append("post_code", post_code);
        formData.append("state", state);
        formData.append("post_town", post_town);
        formData.append("phone", phone);
        formData.append("locality", locality);

        axios.post('/schools', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }) .then(res => {
           console.log(res);
           if(res.status === 200 && !res.data.error){
               showSuccessToast(res.data.message);
               setTimeout(function () {
                   window.location.reload();
               }, 1500)
           }else{
               showErrorToast(res.data.message);
           }
        })

        // $.ajax({
        //     method: 'put',
        //     processData: false,
        //     contentType: false,
        //     cache: false,
        //     data: formData,
        //     enctype: 'multipart/form-data',
        //     url: url,
        //     success: function (response) {
        //         if (response.error === false) {
        //             showSuccessToast(response.message);
        //         } else {
        //             showErrorToast(response.message);
        //         }
        //     }
        // });
    }
</script>
@endsection