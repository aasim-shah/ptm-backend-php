@extends('layouts.master')
@section('title')
{{__('dashboard')}}
@endsection
@section('content')
@inject('utility', 'App\Helpers\Utility')

<style>
    .loader {
        border: 2px solid #f3f3f3;
        border-radius: 50%;
        border-top: 1px solid #3498db;
        width: 20px;
        height: 20px;
        margin: auto;
        -webkit-animation: spin 2s linear infinite; /* Safari */
        animation: spin 2s linear infinite;
    }

    /* Safari */
    @-webkit-keyframes spin {
        0% { -webkit-transform: rotate(0deg); }
        100% { -webkit-transform: rotate(360deg); }
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .message-input-container {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: white;
        border: 1px solid #ece9e9;
        padding: 1rem;

    }

    .chat-left {
        align-self: flex-start;
        text-align: start;
        background-color: #F7F7FC;
        border-radius: 15px;
        min-width: 5%;
        max-width: 50%;
        word-break: break-word;
        padding:0.5rem;
    }
    .chat-right {
        align-self: flex-end;
        text-align: start;
        color: white;
        background-color:  #0B0C0C;
        border-radius: 15px;
        min-width: 5%;
        max-width: 50%;
        word-break: break-word;
        padding:0.5rem;
    }
    .chat-container {
        display: flex;
        flex-direction: column;
        max-height: 400px;
        min-height: 250px;
        overflow-y: auto;
    }
    .element {
        display: inline-flex;
        align-items: center;
    }
    i:hover {
        opacity: 0.6;
    }
    input {
        display: none;
    }
    .border-file{
        border-width: 1px;
        border-style: solid;
        border-color: rgba(231, 230, 230, 0.93);
    }

    .new_message {
        width: 15px;
        height: 15px;
        position: absolute;
        margin-top: -18px;
        margin-left: -18px;
    }
    .new_message:before {
        position: absolute;
        content: '';
        background-color:#FF0000;
        border-radius:50%;
        width: 15px;
        height: 15px;
        pointer-events: none;
    }
    .profile-image{
        width:25px;
        height:25px;
        border-radius: 50px;
    }
    .card {
        background: #fff;
        transition: .5s;
        border: 0;
        margin-bottom: 30px;
        border-radius: .55rem;
        /*position: relative;*/
        width: 100%;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 10%);
    }
    .chat-app .people-list {
        min-width: 255px;
        left: 0;
        top: 0;
        padding: 20px;
        z-index: 7;
    }

    .chat-app .chat {

    }

    .people-list {
        -moz-transition: .5s;
        -o-transition: .5s;
        -webkit-transition: .5s;
        transition: .5s
    }

    .people-list .chat-list .clearfix {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow: hidden;
    }

    .people-list .chat-list .clearfix .about {
        overflow: hidden;
    }

    .people-list .chat-list li {
        padding: 10px 15px;
        list-style: none;
        border-radius: 3px
    }

    .people-list .chat-list li:hover {
        background: #efefef;
        cursor: pointer
    }

    .people-list .chat-list li.active {
        background: #efefef
    }

    .people-list .chat-list li .name {
        font-size: 15px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .people-list .chat-list img {
        width: 45px;
        height: 45px;
        border-radius: 50%
    }

    .people-list img {
        /*float: left;*/
        border-radius: 50%
    }

    .people-list .status {
        color: #999;
        font-size: 13px
    }

    .chat .chat-header {
        padding: 15px 20px;
        border-bottom: 2px solid #f4f7f6
    }

    .chat .chat-header img {
        float: left;
        border-radius: 40px;
        width: 40px
    }

    .chat .chat-header .chat-about {
        float: left;
        padding-left: 10px
    }

    .chat .chat-history {
        padding-left: 20px;
        padding-right: 20px;
        padding-bottom: 20px;
        border-bottom: 2px solid #fff
    }

    .chat .chat-history ul {
        padding: 0
    }

    .chat .chat-history ul li {
        list-style: none;
        margin-bottom: 30px
    }

    .chat .chat-history ul li:last-child {
        margin-bottom: 0px
    }

    .chat .chat-history .message-data {
        margin-bottom: 15px
    }

    .chat .chat-history .message-data img {
        border-radius: 40px;
        width: 40px
    }

    .chat .chat-history .message-data-time {
        color: #434651;
        padding-left: 6px
    }

    .chat .chat-history .message {
        color: #444;
        padding: 18px 20px;
        font-size: 16px;
        border-radius: 7px;
        display: inline-block;
        position: relative
    }

    .chat .chat-history .message:after {
        bottom: 100%;
        left: 7%;
        border: solid transparent;
        content: " ";
        height: 0;
        width: 0;
        position: absolute;
        pointer-events: none;
        border-bottom-color: #fff;
        border-width: 10px;
        margin-left: -10px
    }

    .chat .chat-history .my-message {
        background: #efefef
    }

    .chat .chat-history .my-message:after {
        bottom: 100%;
        left: 30px;
        border: solid transparent;
        content: " ";
        height: 0;
        width: 0;
        position: absolute;
        pointer-events: none;
        border-bottom-color: #efefef;
        border-width: 10px;
        margin-left: -10px
    }

    .chat .chat-history .other-message {
        background: #e8f1f3;
        text-align: right
    }
    p{
        line-height: 20px !important;
    }

    .chat .chat-history .other-message:after {
        border-bottom-color: #e8f1f3;
        left: 93%
    }

    .chat .chat-message {
        padding: 20px
    }

    .online,
    .offline,
    .me {
        margin-right: 2px;
        font-size: 8px;
        vertical-align: middle
    }

    .online {
        color: #86c541
    }

    .offline {
        color: #e47297
    }

    .me {
        color: #1d8ecd
    }

    .float-right {
        float: right
    }

    .clearfix:after {
        visibility: hidden;
        display: block;
        font-size: 0;
        content: " ";
        clear: both;
        height: 0
    }

    @media only screen and (max-width: 767px) {
        .chat-app .people-list {
            height: 465px;
            width: 100%;
            overflow-x: auto;
            background: #fff;
            left: -400px;
            display: none
        }
        .chat-app .people-list.open {
            left: 0
        }
        .chat-app .chat {
            margin: 0
        }
        .chat-app .chat .chat-header {
            border-radius: 0.55rem 0.55rem 0 0
        }
        .chat-app .chat-history {
            height: 300px;
            overflow-x: auto
        }
    }

    @media only screen and (min-width: 768px) and (max-width: 992px) {
        .chat-app .chat-list {
            height: 650px;
            overflow-x: auto
        }
        .chat-app .chat-history {
            height: 600px;
            overflow-x: auto
        }
    }

    @media only screen and (min-device-width: 768px) and (max-device-width: 1024px) and (orientation: landscape) and (-webkit-min-device-pixel-ratio: 1) {
        .chat-app .chat-list {
            height: 480px;
            overflow-x: auto
        }
        .chat-app .chat-history {
            height: calc(100vh - 350px);
            overflow-x: auto
        }
    }
    .active-card {
        background: #f2f2f2 !important;
        display: block !important;
    }
    .error-class::-webkit-input-placeholder {
        color: red
    }
    .card.chat-app {
        display: flex;
        flex-direction: row;
        background: #fff;
        position: relative;
        width: 100%;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 10%);

    }

</style>

<div class="content-wrapper">

    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" />

    <div class="container">
        <div class="row clearfix h-100">
            <div class="col-lg-12">
                <div class="card chat-app">
                    <div id="plist" class="people-list">
                        <div class="input-group">

                        </div>
                        <ul class="list-unstyled chat-list mb-0" style="height: 400px;overflow-y: auto">
                            @if(count($history) === 0)
                            <p>There is no history.</p>
                            @endif

                            @foreach($history as $data)
                            @if($route === 'teacher')
                            <a class="{{ $to_user_id ==  $data->to_user_id ? 'active-card' : ''  }}" href="{{ route('t_chat',$data->receiver ? $data->to_user_id : $data->user_id) }}">
                                @if(isset($data->receiver))
                                <li class="clearfix">
                                    <img class="profile-image" src="{{$data->receiver->image ? $data->receiver->image: asset('/assets/img/user-512.png')}}" alt="avatar">
                                    <div class="about">
                                        <div class="name">{{$data->receiver->first_name}} {{$data->receiver->last_name}}</div>
                                        <div class="status"> <i class="fa fa-circle offline"></i> {{$utility::time_elapsed_string($data->updated_at) }} </div>
                                    </div>
                                </li>
                                @endif
                            </a>
                            @else
                            <a href="{{ route('p_chat',$data->receiver ? $data->to_user_id : $data->user_id) }}">
                                @if(isset($data->receiver))
                                <li class="clearfix">
                                    <img class="profile-image" src="{{$data->receiver->image ? $data->receiver->image: asset('/assets/img/user-512.png')}}" alt="avatar">
                                    <div class="about">
                                        <div class="name">{{$data->receiver->first_name}} {{$data->receiver->last_name}}</div>
                                        <div class="status"> <i class="fa fa-circle offline"></i> {{ $utility::time_elapsed_string($data->updated_at) }} </div>
                                    </div>
                                </li>
                                @else
                                <li class="clearfix">
                                    <img class="profile-image" src="{{$data->user->image}}" alt="avatar">
                                    <div class="about">
                                        <div class="name">{{$data->user->first_name}} {{$data->user->last_name}}</div>
                                        <div class="status"> <i class="fa fa-circle offline"></i> {{ $utility::time_elapsed_string($data->updated_at) }} </div>
                                    </div>
                                </li>
                                @endif
                            </a>
                            @endif
                            @endforeach
                        </ul>
                    </div>
                    @if(isset($chats))
                    <div class="chat" style="width: 100%">
                        <div class="chat-header clearfix mt-1">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="chat-about">
                                        <h6 class="m-b-0">{{$to_user_name}}</h6>
                                        <small>{{ $utility::time_elapsed_string($chats->updated_at) }}</small>
                                    </div>
                                </div>
                                <!--                                <div class="col-lg-4 hidden-sm text-right"></div>-->
                                <div class="col-md-2 hidden-sm text-right">

                                    <!--                                    <div class="element pr-3 pl-3 border-file">-->
                                    <!--                                        <i class="btn btn-outline-primary"></i><span class="name"></span>-->
                                    <!--                                        <input type="file" name="file" id="file">-->
                                    <!--                                    </div>-->
                                </div>
                            </div>
                        </div>
                        <div class="chat-history" id="chat-history">
                            <ul class="m-b-0">
                                <div class="card-body" style="display: flex;flex-direction: column-reverse;">
                                    <div class="chat-container">

                                    </div>
                                </div>
                            </ul>
                        </div>
                        <div class="chat-message clearfix">
                            <form action="{{ route('create-chat') }}" method="post">
                                @csrf
                                <div class="form-group">
                                    <div class="input-group mb-0">
                                        <div class="element pr-3 pl-3 border-file" style="height: 46px; overflow: hidden;">
                                            <i class="fa fa-file"></i><span class="name" style="max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                                            <input type="file" name="file" id="file">
                                        </div>
                                        <input id="message" name="message" type="text" class="form-control"  placeholder="Enter text here...">
                                        <div class="input-group-prepend" >
                                            <button style="color: white; background-color: #0a58ca" type="button" class="form-control" onclick="sendMessages()">Send</button>
                                        </div>
                                        <input hidden type="text" id="to_user" name="to_user" class="form-control" value="{{$to_user_id}}">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@section('script')

<script>
    try{
        var db = firebase.firestore();
        const user_id = '{{auth()->user()->id}}';
        const chatId = '{{$chatId}}';
        var chatMessagesRef = db.collection('messages').
        doc(chatId).collection('chatMessages').orderBy('data.created_at');

        chatMessagesRef.onSnapshot(function(snapshot) {
            snapshot.docChanges().forEach(function(change) {
                if (change.type === 'added') {
                    var messageData = change.doc.data();
                    let body = messageData.data;

                    if(body.message === null){
                        body.message = '';
                    }
                    if(body.file && body.is_image){

                        if(user_id == body.sender_id){

                            $(".chat-container").append(' <p class="chat chat-right"> ' +
                                '<span> '+body.message+'' +
                                '<embed src="'+body.file+'" width="100%"> </span> </p>');

                        }
                        if(user_id != body.sender_id){
                            $(".chat-container").append(' <p class="chat chat-left"> ' +
                                '<span> '+body.message+'' +
                                '<embed src="'+body.file+'" width="100%"> </span> </p>');

                        }

                    }else if (body.file && !body.is_image){
                        if(user_id == body.sender_id) {

                            $(".chat-container").append(' <p class="chat chat-right"> ' +
                                '<span> '+body.message+'' +
                                ' <br> <a href="'+body.file+'" target="_blank">download file</a> </span> </p>');

                        }
                        if(user_id != body.sender_id){

                            $(".chat-container").append(' <p class="chat chat-left"> ' +
                                '<span>'+body.message+'' +
                                '<br> <a href="'+body.file+'" target="_blank">download file</a> </span> </p>');

                        }

                    }else{
                        if(user_id == body.sender_id) {
                            $(".chat-container").append(' <p class="chat chat-right"> ' +
                                '<span> '+body.message+'' +
                                ' </span> </p>');

                        }
                        if(user_id != body.sender_id){

                            $(".chat-container").append(' <p class="chat chat-left"> ' +
                                '<span> '+body.message+'' +
                                ' </span> </p>');
                        }
                    }
                }
            });

        });
        // window.setInterval(function() {
        //     var elem = document.getElementById('chat-container');
        //     elem.scrollTop = 5000;
        // }, 3000);
    }catch (error){
        console.log(error);
    }
</script>

<script>


    const messagings = firebaseInit.messaging();
    messagings.usePublicVapidKey("BMF317nYWC_7EZ2EcldU5OS2IhGO1UE0yXWV7TgBZ1mekcXe5BTrckxBqRhiWIWzglBvWbtAWST1md1C5rTZiz4");

    function sendTokenToServer(fcm_token) {
        const user_id = '{{auth()->user()->id}}';
        axios.post('/save-token', {
            fcm_token, user_id
        })
            .then(res => {
                console.log(res);
            })
    }

    function retreiveToken() {
        messagings.getToken().then((currentToken) => {
            if (currentToken) {
                sendTokenToServer(currentToken);
            } else {

                alert('You should allow notification!');
            }
        }).catch((err) => {
            console.log(err.message);
        });
    }

    retreiveToken();
    messagings.onTokenRefresh(() => {
        console.log('onTokenRefresh');
        retreiveToken();
    });

    messagings.onMessage((res) => {
        console.log('Message received');
    });

</script>
<script>
    if(document.getElementById("to_user").value == null ||
        document.getElementById("to_user").value === ''){
        $('.message-input-container').hide();
    }
    $('.loader').hide();

    var user = {!! auth()->user()->toJson() !!}
    function sendMessages() {

        $('.loader').show();
        var formData = new FormData();

        const message = document.getElementById("message").value;
        const id = document.getElementById("to_user").value;
        var imagefile = document.querySelector('#file');
        if (!message && !imagefile.value) {
            var messageVal = document.getElementsByName('message');
            messageVal[0].placeholder = "Please enter something";
            $('#message').addClass('error-class');
        }else {
            formData.append("file", imagefile.files[0]);
            formData.append("message", message);
            formData.append("id", id);
            // if(message || !imagefile.files[0] === ''){
            //     return;
            // }
            axios.post('/chat', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }) .then(res => {
                $('.loader').hide();
                   document.getElementById("message").placeholder = 'Enter text here...';
                   document.getElementById("message").value = '';
                   $('#message').removeClass('error-class');
                   $('input[type="file"]').siblings('span').text('');

                $('#file').val('');
            })
        }
    }
    $("i").click(function () {
        $("input[type='file']").trigger('click');
    });

    $('input[type="file"]').on('change', function() {
        var val = this.files[0].name;
        $(this).siblings('span').text(val);
    })
    function timeSince(date) {

        var seconds = Math.floor((new Date() - date) / 1000);

        var interval = seconds / 31536000;

        if (interval > 1) {
            return Math.floor(interval) + " years";
        }
        interval = seconds / 2592000;
        if (interval > 1) {
            return Math.floor(interval) + " months";
        }
        interval = seconds / 86400;
        if (interval > 1) {
            return Math.floor(interval) + " days";
        }
        interval = seconds / 3600;
        if (interval > 1) {
            return Math.floor(interval) + " hours";
        }
        interval = seconds / 60;
        if (interval > 1) {
            return Math.floor(interval) + " minutes";
        }
        return Math.floor(seconds) + " seconds";
    }
    $(document).ready(function() {
        $(document).keypress(function(event) {
            if (event.which === 13) {
                sendMessages();
            }
        });
    });
</script>
<script>
    var chatContainer = $('.chat-container');
    chatContainer.scrollTop(chatContainer[0].scrollHeight);
</script>
@endsection
@endsection

