@extends('layouts.master')
@section('title')
@section('content')

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    .fc-event-main{
        color: black !important;
    }
    .fc .fc-event{
        color: black !important;
    }
    .fc .fc-button-primary{
        color: black;
    }
    .fc-icon-chevron-left::before {
        font-family: 'FontAwesome';
        content: "\f053";
    }
    .fc-icon-chevron-right::before {
        font-family: 'FontAwesome';
        content: "\f054";
    }
    .select2-search__field{
        width: 100% !important;
        height: 37px;
    }
</style>
<div class="content-wrapper bg-white" style="padding-bottom: unset">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-theme text-white mr-2">
                <i class="fa fa-calendar"></i>
            </span> {{__('Calendar')}}
        </h3>
    </div>

    <div class="container" style="height: 630px;overflow-y: scroll">
        <div class="row justify-content-center w-100">
            <div class="col-md-12">
                <div class="float-right">
                    <a data-toggle="modal" data-target="#editModal">
                        <button class="btn btn-facebook">+ New Meeting</button>
                    </a>
                </div>
            </div>
        </div>
        <div class="row justify-content-center w-100">
            <div class="col-md-12">
                <div class="m-5" id='calendar'></div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editModal" data-backdrop="static" tabindex="-1" role="dialog"
     aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ __('add_meeting') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <form id="add-form" class="addForm" novalidate="novalidate"
                  enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('title') }} <span class="text-danger">*</span></label>
                            {!! Form::text('title', null, ['required', 'placeholder' => __('title'), 'class' =>
                            'form-control title', 'id' => 'title']) !!}

                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('description') }} <span class="text-danger">*</span></label>
                            {!! Form::text('description', null, ['required', 'placeholder' => __('description'), 'class' =>
                            'form-control description', 'id' => 'description']) !!}
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('date') }} <span class="text-danger">*</span></label>
                            {!! Form::date('date', null, ['required', 'placeholder' => __('date'), 'class' =>
                            'form-control date', 'id' => 'date']) !!}

                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('start_time') }}<span class="text-danger">*</span></label>
                            {!! Form::time('meeting_time', null, [ 'placeholder' => __('start_time'), 'class' =>
                            'form-control meeting_time', 'id' => 'meeting_time']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('end_time') }}<span class="text-danger">*</span></label>
                            {!! Form::time('meeting_end_time', null, [ 'placeholder' => __('end_time'), 'class' =>
                            'form-control meeting_end_time', 'id' => 'meeting_end_time']) !!}
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('teacher') }} <span class="text-danger">*</span></label>
                            <select class="form-control selectTeacher" name="teacher">
                                <option value="">select teacher</option>
                                @foreach($teachers as $teacher)
                                <option value="{{$teacher->id}}"> {{$teacher->first_name}} {{$teacher->last_name}}
                                </option>
                                @endforeach

                            </select>
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('classes') }} <span class="text-danger">*</span></label>
                            <select class="form-control selectClasses" name="class">
                                <option value="">select class</option>
                            </select>
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('students') }} <span class="text-danger">*</span></label>
                            <select class="selectStudent" multiple="multiple" name="students[]">
                                <option></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input id="submitButton" class="btn btn-theme" onclick="submitMeeting()" type="button" value={{ __('submit') }}>
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ __('cancel') }}</button>
                </div>
            </form>


        </div>
    </div>
</div>

@section('script')
<script src="{{asset('js/select2.js')}}"></script>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>
<script>
    $(document).ready(function() {
        $selectElement = $('.selectStudent').select2({
            placeholder: "Select user",
            allowClear: true
        });
    });
</script>
<script>
    $('.selectStudent').select2();
    $('.selectTeacher').on('change', function () {
        let teacherId = this.value;
        $('.selectClasses').find('option')
            .remove()
            .end();
        $('.selectStudent').find('option')
            .remove()
            .end();
        if (teacherId && teacherId !== 'undefined') {

            let url = "{{ route('teacher_classes', ':id') }}";
            url = url.replace(':id', teacherId);

            $.ajax({
                url: url,
                type: "GET",
                success: function (response) {
                    $('.selectClasses').append('<option value="">' + 'select class' + '</option>');

                    response.forEach(function (data) {
                        console.log(data);
                        $('.selectClasses').append('<option value="' + (data.id) + '">' + data.name + '</option>');
                    });
                }
            });
        }
    });
    $('.selectClasses').on('change', function () {
        let classId = this.value;
        $('.selectStudent').find('option')
            .remove()
            .end();
        if (classId && classId !== 'undefined') {

            let url = "{{ route('class_students', ':id') }}";
            url = url.replace(':id', classId);

            $.ajax({
                url: url,
                type: "GET",
                success: function (response) {
                    $('.selectStudent').append('<option value="ALL">' + 'All Students' + '</option>');
                    response.forEach(function (data) {
                        $('.selectStudent').append('<option value="' + (data.id) + '">' + data.first_name + ' ' + data.last_name + '</option>');
                    });
                }
            });
        }
    });

    function submitMeeting(){
        document.getElementById("submitButton").disabled = true;
        let url = "{{ route('create_meeting') }}";
        let data = {
            "title":$(".title").val(),
            "class_id":$(".selectClasses").val(),
            "description":$(".description").val(),
            "meeting_date":$(".date").val(),
            "meeting_time":$(".meeting_time").val(),
            "meeting_end_time":$(".meeting_end_time").val(),
            "teacher_id":$(".selectTeacher").val(),
            "student_ids":$(".selectStudent").val(),
        }

        $.ajax({
            url: url,
            type: "POST",
            data: data,
            success: function (response) {
                if (response.error === false) {
                    $("#add-form")[0].reset()
                    $('.selectStudent').val(null).trigger('change');
                    showSuccessToast(response.message);
                    $('#editModal').modal('toggle');
                    document.getElementById("submitButton").disabled = false;
                    setTimeout(function () {
                        location.reload();
                    }, 1000);

                } else {
                    document.getElementById("submitButton").disabled = false;
                    showErrorToast(response.message);

                }
            }
        });
    }

</script>
<script>

    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');

        var calendars = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth'
            },
            views: {
                dayGridMonth: { // name of view
                    // titleFormat: { year: 'numeric', month: '2-digit', day: '2-digit' }
                    // other view-specific options here
                    dayMaxEvents : 2
                }
            },
            eventClick: function (info) {
                let url = "{{ route('meeting_details', ':id') }}";
                url = url.replace(':id', info.event.extendedProps.meeting_hash);
                window.location.href =url
            }
        });
        let url = "{{ route('meetings', ':status') }}";
        url = url.replace(':status', null);
        $.ajax({
            url: url,
            type: "GET",
            success: function (response) {
                response.forEach(function (data) {
                    calendars.batchRendering(function () {
                        calendars.addEvent(
                            {
                                id: 1,
                                title: data.title,
                                description: data.description,
                                start: data.meeting_date + ' ' + data.meeting_time,
                                end: data.meeting_date + ' ' + data.meeting_end_time,
                                textColor: '#faf7f7',
                                backgroundColor: 'rgba(3,3,3,0.53)',
                                editable: true,
                                extendedProps:{
                                    meeting_hash: data.meeting_hash,
                                }
                            });
                        calendars.on('eventDrop', function (info) {
                            const updatedEvent = info.event;
                            console.log('Event dropped:', updatedEvent);
                        });
                        calendars.on('eventResize', function (info) {
                            const updatedEvent = info.event;
                            console.log('Event resized:', updatedEvent);
                        });
                    })
                    calendars.render();
                });
                if (response.length == 0){
                    calendars.render();
                }
            }
        });

    });

</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var dateInput = document.getElementById('date');

        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0');
        var yyyy = today.getFullYear();

        today = yyyy + '-' + mm + '-' + dd;
        dateInput.setAttribute('min', today);
    });
</script>


@endsection
@endsection

