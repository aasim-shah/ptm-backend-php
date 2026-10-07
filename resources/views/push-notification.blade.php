@extends('layouts.master')

@section('title')
Push Notifications
@endsection

@section('content')
<style>
    .select2-container--default .select2-selection--multiple {
        height: 45px;
    }
</style>
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            Push Notifications
        </h3>
    </div>
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
<!--                    <h4 class="card-title">-->
<!--                        {{ __('Send'). ' '.__('Notification') }}-->
<!--                    </h4>-->

                    <form class="create-form pt-3 mt-3" id="formdata" action="{{url('send-notification')}}"
                          method="POST"
                          novalidate="novalidate">
                        @csrf

                        <div class="row mt-3">
                            @if(isset($schools))

                            <div class="col-3 form-group"><label for="school_id">{{ __('School') }}</label><span class="text-danger"> *</span>
                                <select onchange="selectSchool(this.value)" name="school_id" id="school_id"
                                        class="form-control select2">
                                    @if(auth()->user()->type === 'admin')
                                    <option disabled selected value="">{{ __('select'). ' '.__('school') }}</option>
                                    <option value="ALL">All</option>
                                    @endIf
                                    @foreach ($schools as $school)
                                    <option value="{{ $school->id }}">{{ $school->school_name }} - {{ $school->address
                                        }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @endIf
                            <div class="col-3 form-group"><label for="school_id">{{ __('User Type') }}</label><span class="text-danger"> *</span>
                                <select onchange="selectUserType(this.value)" name="user_type" id="user_type"
                                        class="form-control">
                                    <option value="">{{ __('select'). ' '.__('user type') }}</option>
                                    <option value="ALL">All</option>
                                    <option value="teacher">Teachers</option>
                                    <option value="parent">Parents</option>
                                </select>
                            </div>
                            <div class="col-3 form-group"><label for="school_id">{{ __('Class') }}</label><span class="text-danger"> *</span>
                                <select onchange="selectClass(this.value)" name="filter_class_section_id" id="filter_class_section_id"
                                        class="form-control">
                                    <option value="">{{ __('select_class_section') }}</option>
                                    <option value="ALL">All</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('Users') }}<span class="text-danger">*</span></label>
                                <select name="user_ids[]" multiple="multiple" id="user_ids"
                                        class="form-control">
                                    <option></option>
                                    <option value="">{{ __('select user') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('title') }} <span class="text-danger">*</span></label>
                                {!! Form::text('title', null, [ 'placeholder' => __('title'), 'class' =>
                                'form-control']) !!}
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('Message') }} <span class="text-danger">*</span></label>
                                {!! Form::text('message', null, [ 'placeholder' => __('Message'), 'class' =>
                                'form-control']) !!}
                            </div>

                        </div>

                    </form>
                    <input class="btn theme-green float-right" id="submitBtn" type="submit" value={{ __('Send') }}>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
@section('script')
<script src="{{asset('js/select2.js')}}"></script>
<script>
    $(document).ready(function() {
        $selectElement = $('#user_ids').select2({
            placeholder: "Select user",
            allowClear: true
        });
    });

</script>
<script>
    $('#user_ids').select2();

    function selectUserType(type){
        var classSectionSelect = $('#filter_class_section_id');
        var user_ids = $('#user_ids');
        user_ids.find('option').remove();

        let id = $('#school_id').val();
        if (id !== '' && id !== 'undefined') {
            let url = "{{ route('class-section-school', [':school_id',':type']) }}";
            url = url.replace(':school_id', id);
            url = url.replace(':type', type);
            classSectionSelect.find('option').remove();
            $.ajax({
                url: url,
                type: "GET",
                success: function (data) {
                    classSectionSelect.append("<option selected value=''>Select Class</option>");
                    classSectionSelect.append("<option value='ALL'>All</option>");
                    for (let i = 0; i < data.length; i++) {
                        let class_section_id = data[i].class_id;
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

    function selectSchool(id) {
        var classSectionSelect = $('#filter_class_section_id');
        classSectionSelect.find('option').remove();
        classSectionSelect.append("<option selected value=''>Select Class</option>");
        var userType = $('#user_type');
        this.selectUserType(userType);
    }

    function selectClass(id) {
        var classSectionSelect = $('#filter_class_section_id');
        var userType = $('#user_type');
        var school_id = $('#school_id');
        var user_ids = $('#user_ids');

        user_ids.find('option').remove();


        if (classSectionSelect.val() !== '' && classSectionSelect.val() !== 'undefined') {
            let url = "{{ route('class-users', [':class_id',':type',':school_id']) }}";
            url = url.replace(':class_id', classSectionSelect.val());
            url = url.replace(':type', userType.val());
            url = url.replace(':school_id', school_id.val());

            $.ajax({
                url: url,
                type: "GET",
                success: function (data) {
                    if (data.length == 0 ){
                        user_ids.append("<option value='' disabled>No records found</option>");
                    }else {
                        user_ids.append("<option value='ALL'>All</option>");

                        for (let i = 0; i < data.length; i++) {
                            let user_id = data[i].id;
                            user_ids.append("<option value=" + user_id + " > " + data[i].first_name + " " + data[i].last_name + "</option>");
                        }
                    }
                },
                error: function (error) {
                    console.log(`Error ${error}`);
                }
            });
        } else {
            user_ids.find('option').remove();
        }
    }

        $('#submitBtn').on("click", function(event) {
            event.preventDefault();
            $.ajax({
                url: $('#formdata').attr('action'),
                type: $('#formdata').attr('method'),
                data: $('#formdata').serialize(),
                success: function (response) {
                    if (response.error == false){
                        $('#user_ids').val(null).trigger('change');
                        $('#formdata')[0].reset();
                        $('#user_ids').empty();
                        $('#user_ids').append('<option value="">{{ __('select user') }}</option>');

                        showSuccessToast(response.message);
                    }else {
                        showErrorToast(response.message);
                    }
                }
            });
        });



    $('#user_ids').select2({
        sorter: function(results) {
            var query = $('.select2-search__field').val().toLowerCase();
            return results.sort(function(a, b) {
                return a.text.toLowerCase().indexOf(query) -
                    b.text.toLowerCase().indexOf(query);
            });
        }
    });

</script>
@endsection
