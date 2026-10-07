@extends('layouts.master')

@section('title')
{{ __('Meeting') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
           Meeting Details
        </h3>
    </div>

    <div class="row">

        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        Meeting List
                    </h4>
                    <div class="row">
                        @if(isset($classes))

                        <div class="col-4">
                            <select name="class_id" id="class_id"
                                    class="form-control select2">
                                <option value="">{{ __('select'). ' '.__('class') }}</option>
                                @foreach ($classes as $class)
                                 @if(count($class->sections)>0)
                                    <option value="{{ $class->id }}">{{ $class->name }} - {{ $class->sections[0]->name
                                        }}
                                    </option>
                                 @else
                                    <option value="{{ $class->id }}">{{ $class->name }}
                                    </option>
                                 @endif
                                @endforeach
                            </select>
                        </div>
                        @endIf
                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                   data-url="{{ route('meeting_list') }}" data-click-to-select="true"
                                   data-side-pagination="server" data-pagination="true"
                                   data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                   data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                   data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                                   data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                   data-maintain-selected="true" data-export-types='["txt","excel"]'
                                   data-export-options='{ "fileName": "teacher-list-<?= date('d-m-y') ?>" ,"ignoreColumn":
                                    ["operate"]}'
                                   data-query-params="meetingQueryParams" data-check-on-init="true">
                                <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}</th>
                                    <th scope="col" data-field="meeting_hash" data-sortable="true" data-visible="false">
                                        {{ __('meeting_hash') }}</th>
                                    <th scope="col" data-field="title" data-sortable="false">Title
                                    </th>
                                    <th scope="col" data-field="date" data-sortable="false">
                                        Date</th>
                                    <th scope="col" data-field="is_principal" data-sortable="false">
                                        Principal</th>
                                    <th scope="col" data-field="meeting_time" data-sortable="false">
                                        Start Time</th>
                                    <th scope="col" data-field="meeting_end_time" data-sortable="false">
                                        End Time</th>
                                    <th scope="col" data-field="status" data-sortable="false">
                                        Status</th>
                                    <th data-events="meetingActionEvent" scope="col" data-field="operate"
                                        data-sortable="false">{{ __('action') }}</th>
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
                <h5 class="modal-title" id="exampleModalLabel">Manage Meeting</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <div class="modal-body">
                <label>{{ __('description') }} </label>
                {!! Form::text('description', null, [ 'placeholder' => __('description'), 'class' => 'form-control', 'id' => 'description']) !!}

                <div hidden>
                <label>{{ __('meeting_hash') }} </label>
                {!! Form::text('meeting_hash', null, [ 'placeholder' => __('meeting_hash'), 'class' => 'form-control', 'id' => 'meeting_hash']) !!}
                {!! Form::text('meeting_id', null, [ 'placeholder' => __('meeting_id'), 'class' => 'form-control', 'id' => 'meeting_id']) !!}
                </div>
            </div>
            <div class="modal-footer">
                <input class="btn btn-theme" onclick="acceptMeeting(true)" id="acceptBtn" type="submit" value="Accept">
                <input class="btn btn-danger" onclick="acceptMeeting(false)" type="submit" id="rejectBtn" value="Reject">
                <button type="button" class="btn btn-light" id="cancelBtn" data-dismiss="modal">{{ __('cancel') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
<script>
    function acceptMeeting(isAccept){
        let meeting_hash = $("#meeting_hash").val();
        let meeting_id = $("#meeting_id").val();
        let url = "{{ route('update_meeting') }}";
        $.ajax({
            url: url,
            type: "POST",
            data: {
                meeting_hash: meeting_hash,
                status: isAccept,
                id: meeting_id,
            },
            success: function (response) {
                if (response.error === false) {
                    showSuccessToast(response.message);
                    $('#editModal').modal('toggle');
                    $('#table_list').bootstrapTable('refresh');

                } else {
                    showErrorToast(response.message);
                }
            }
        });

    }

    function selectSchool(id) {

        // if (id !== '' && id !== 'undefined') {
        //     var table = $('#table_list').DataTable({});
        //     table.ajax.url( 'teacher_list/'+id ).load();
        // }

        if (id !== '' && id !== 'undefined') {
            // $('.table').dataTable().api().ajax.url('teacher_list/'+id ).load();
        }
    }
</script>
@endsection
