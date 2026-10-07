
<!DOCTYPE html>
<html>

<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title>Video Streams</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"crossorigin="anonymous"></script>

    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <style>
        body{
            background:#0F2027;
            background:-webkit-linear-gradient(to right, #2C5364, #203A43, #0F2027) ;
            background: linear-gradient(to right, #2C5364, #203A43, #0F2027);
            overflow: scroll;
            margin: 10px;
            max-height: fit-content;
            overflow-x: scroll;
        }

        #join-btn{
            position: absolute;
            top:50%;
            left:50%;
            margin-top:-50px;
            margin-left:-100px;
            font-size:18px;
            padding:20px 40px;
        }

        #join-btns{
            position: absolute;
            top: 50%;
            left: 36%;
            margin-top: -50px;
            margin-left: -100px;
            font-size: 18px;
            padding: 20px 40px;
        }
        #join-btn1{
            position: absolute;
            top: 50%;
            left: 35%;
            margin-top: -50px;
            margin-left: -100px;
            font-size: 18px;
            padding: 20px 40px;
        }
        #join-btn2{
            position: absolute;
            top: 50%;
            left: 51%;
            margin-top: -50px;
            margin-left: -100px;
            font-size: 18px;
            padding: 20px 40px;
        }
        #dataTable{
            position: absolute;
            top: 64%;
            /* left: 28%; */
            margin-top: -50px;
            margin-left: -100px;
            font-size: 18px;
            padding: 20px 40px;
            color: whitesmoke;
            border-color: #fff;
            /* height: 300px; */
            /* width: 592px; */
            overflow: scroll;
        }
        #dataTable2{
            position: absolute;
            top: 64%;
            /* left: 18%; */
            /* margin-top: -50px; */
            margin: 20px;
            font-size: 18px;
            padding: 20px 40px;
            color: whitesmoke;
            border-color: #fff;
            /* height: 350px; */
            /* width: 592px; */
            overflow: scroll;
        }
        #linkUrl{
            position: absolute;
            top: 34%;
            left: 36%;
            width: 283px;
            margin-top: -50px;
            margin-left: -100px;
            font-size: 18px;
            padding: 20px 40px;
            background-color: #9bcbcd;
        }
        #linkname{
            position: absolute;
            top: 22%;
            left: 36%;
            width: 283px;
            margin-top: -50px;
            margin-left: -100px;
            font-size: 18px;
            padding: 20px 40px;
            background-color: #9bcbcd;
        }
        #video-streams{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            height: 90vh;
            width: 1400px;
            margin:0 auto;
        }

        .video-container{
            max-height: 100%;
            border: 2px solid black;
            background-color: #203A49;
        }

        .video-player{
            height: 100%;
            width: 100%;
        }

        button{
            border:none;
            background-color: cadetblue;
            color:#fff;
            padding:10px 20px;
            font-size:16px;
            margin:2px;
            cursor: pointer;
        }

        #stream-controls{
            display: none;
            justify-content: center;
            margin-top:0.5em;
        }

        @media screen and (max-width:1400px){
            #video-streams{
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                width: 95%;
            }
        }
    </style>
</head>

<body >
<input type="text" id="linkUrl" value="" placeholder="Enter Link">

<button id="join-btn1" onclick="joinUserMeeting()">Join Meeting</button>

@if(Auth::User())
<a href="{{url('createMeeting')}}"><button id="join-btn2">create Meeting</button></a>
@endif









</body>
<!-- <script src="https://download.agora.io/sdk/release/AgoraRTC_N.js"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    function joinUserMeeting()
    {
        var link  = $('#linkUrl').val();
        if(link.trim() == '' || link.length < 1){
            alert('PLease enter the link');
            return;
        }else{
            window.location.href = link;
        }
    }
</script>

</html>