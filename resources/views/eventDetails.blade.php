@extends('layouts.master')

@section('title', 'Meeting Details')

@section('content')
<script src="{{ mix('js/app.js') }}" defer></script>
<meta name="csrf-token" content="{{ Session::token() }}">
<style>
    #video-container{
        margin-top: -50% !important;
        z-index: 1 !important;
    }
    .action-btns{
        left: 40% !important;
        margin-left: 0;
    }
    .new-status{
        background: rgba(211, 211, 211, 0.85);
    }
    .accepted-status{
        background: rgba(147, 255, 147, 0.5);
    }
    .rejected-status{
        background: rgba(255, 146, 146, 0.5);
    }
</style>
<div class="content-wrapper" style="font-size: 14px">
    <div class="page-header">
        <h3 class="page-title">
            Meeting Details
        </h3>
    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                 <div class="card-body">
            @foreach($meetingDetails as $meetingDetail)
            <div class="meeting-info">
                <h5 class="card-title mb-3">Meeting Information</h5>
                <div class="row mb-3 pl-4">
                    <div class="col-sm-3 font-weight-bold">Title:</div>
                    <div class="col-sm-9">{{ $meetingDetail->title }}</div>
                </div>

                <div class="row mb-3 pl-4">
                    <div class="col-sm-3 font-weight-bold">Description:</div>
                    <div class="col-sm-9">{{ $meetingDetail->description }}</div>
                </div>

                <div class="row mb-3 pl-4">
                    <div class="col-sm-3 font-weight-bold">Meeting Date:</div>
                    <div class="col-sm-9">{{ $meetingDetail->meeting_date }}</div>
                </div>

                <div class="row mb-3 pl-4">
                    <div class="col-sm-3 font-weight-bold">Meeting Time:</div>
                    <div class="col-sm-9">{{ $meetingDetail->meeting_time }}</div>
                </div>

                <div class="row mb-3 pl-4">
                    <div class="col-sm-3 font-weight-bold">Meeting End Time:</div>
                    <div class="col-sm-9">{{ $meetingDetail->meeting_end_time }}</div>
                </div>
            </div>
            @break
            @endforeach

            <div class="participants-info ">
                <h5 class="card-title pt-3 mb-3">Participants Information</h5>

                @foreach($meetingDetails as $meetingDetail)
                @if($meetingDetail->teacher)
                <div class="row p-4 {{
                     $meetingDetail->status == 'new' ? 'new-status':
                    ( $meetingDetail->status == 'accepted' ? 'accepted-status' : 'rejected-status')

                    }}">

                        <div class="col-sm-6">
                            <div class="row mb-3">
                                <div class="col font-weight-bold">Teacher Name:</div>
                                <div class="col">{{ $meetingDetail->teacher->first_name }}
                                    {{ $meetingDetail->teacher->last_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="row mb-3">
                                <div class="col font-weight-bold">Teacher Email:</div>
                                <div class="col">{{ $meetingDetail->teacher->email }}</div>
                            </div>
                        </div>

                </div>
                @endif
                @endforeach
                <hr>
                @foreach($meetingDetails as $meetingDetail)
                @if($meetingDetail->parent)
                <div class="row p-4 {{
                     $meetingDetail->status == 'new' ? 'new-status':
                    ( $meetingDetail->status == 'accepted' ? 'accepted-status' : 'rejected-status')

                }}">

                    <div class="col-sm-6">
                        @if($meetingDetail->student)
                        <div class="row mb-3">
                            <div class="col font-weight-bold">Student Name:</div>
                            <div class="col">{{ $meetingDetail->student->first_name }}
                                {{ $meetingDetail->student->last_name}}
                            </div>
                        </div>
                        @endif

                        <div class="row mb-3">
                            <div class="col font-weight-bold">Parent Name:</div>
                            <div class="col">{{ $meetingDetail->parent->first_name }}
                                {{ $meetingDetail->parent->last_name }}
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        @if($meetingDetail->student)
                        <div class="row mb-3">
                            <div class="col font-weight-bold">Gender</div>
                            <div class="col"><div>
                                    {{ $meetingDetail->student->gender }}
                                </div></div>
                        </div>
                        @endif

                        <div class="row mb-3">
                            <div class="col font-weight-bold">Parent Email:</div>
                            <div class="col">{{ $meetingDetail->parent->email }}</div>
                        </div>
                    </div>
                </div>
                <hr>
@endif
                @endforeach

            </div>
            <div id="app" class="float-right">
                <agora-chat :allusers="{{json_encode($user)}}" authuserid="{{ auth()->id() }}"
                            authuser="{{ auth()->user()->id }}"
                            channelname="{{ $meetingDetails->first()->meeting_hash }}"
                            agora_id="{{ config('environment.AGORA_APP_ID') }}"></agora-chat>
            </div>
        </div>
            </div>
        </div>
    </div>
</div>

@endsection


