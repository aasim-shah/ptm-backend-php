@extends('layouts.app')
@section('title')
{{__('dashboard')}}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-theme text-white mr-2">
                <i class="fa fa-home"></i>
            </span> Call
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12">
            <h2 class="left-align">Get started with video calling</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <button type="button" id="join">Join</button>
            <button type="button" id="leave">Leave</button>
        </div>
    </div>
</div>

<script type="application/javascript">
    // import AgoraRTC from "agora-rtc-sdk-ng"
    var urlParams = new URLSearchParams(window.location.search);
    var id = urlParams.get('id');
    var options =
        {
            appId: 'e0b79f7364044422aa152978dd675b02',
            channel: 'schoolNow',
            token: '007eJxTYDjlteSQ1ZHCMu4vj50VnylNfTypato7tf8mPAKRx/3uzC1QYEg1SDK3TDM3NjMxMDExMTJKTDQ0NbI0t0hJMTM3TTIw6nbgTW0IZGT489yTgREKQXxOhuLkjPz8HL/8cgYGABxzIZk=',
            uid: {{ auth()->id() }}
      }

    var channelParameters =
        {
            localAudioTrack: null,
            localVideoTrack: null,
            remoteAudioTrack: null,
            remoteVideoTrack: null,
            remoteUid: id,
        };

    async function startBasicCall() {

        const agoraEngine = AgoraRTC.createClient({mode: "rtc", codec: "h264"});
        const remotePlayerContainer = document.createElement("div");
        const localPlayerContainer = document.createElement('div');
        localPlayerContainer.id = options.uid;
        localPlayerContainer.textContent = "Local user " + options.uid;
        localPlayerContainer.style.width = "640px";
        localPlayerContainer.style.height = "480px";
        localPlayerContainer.style.padding = "15px 5px 5px 5px";
        remotePlayerContainer.style.width = "640px";
        remotePlayerContainer.style.height = "480px";
        remotePlayerContainer.style.padding = "15px 5px 5px 5px";
        agoraEngine.on("user-published", async (user, mediaType) => {

            if (user.uid == id) {

                await agoraEngine.subscribe(user, mediaType);
                console.log(user.uid);
                if (mediaType === "video") {
                    channelParameters.remoteVideoTrack = user.videoTrack;
                    channelParameters.remoteAudioTrack = user.audioTrack;
                    channelParameters.remoteUid = user.uid.toString();
                    remotePlayerContainer.id = user.uid.toString();
                    channelParameters.remoteUid = user.uid.toString();
                    remotePlayerContainer.textContent = "Remote user " + user.uid.toString();
                    document.body.append(remotePlayerContainer);
                    channelParameters.remoteVideoTrack.play(remotePlayerContainer);
                }
                if (mediaType === "audio") {
                    channelParameters.remoteAudioTrack = user.audioTrack;
                    channelParameters.remoteAudioTrack.play();
                }
                agoraEngine.on("user-unpublished", user => {
                });
            }
        });
        agoraEngine.on("token-privilege-will-expire", async function ()
        {
            options.token = await fetchToken(options.uid, options.channel);
            await agoraEngine.renewToken(options.token);
        });
        window.onload = function () {
            document.getElementById("join").onclick = async function () {
                await agoraEngine.join(options.appId, options.channel, options.token, options.uid);
                channelParameters.localAudioTrack = await AgoraRTC.createMicrophoneAudioTrack();
                channelParameters.localVideoTrack = await AgoraRTC.createCameraVideoTrack();
                document.body.append(localPlayerContainer);
                await agoraEngine.publish([channelParameters.localAudioTrack, channelParameters.localVideoTrack]);
                channelParameters.localVideoTrack.play(localPlayerContainer);
            }
            document.getElementById('leave').onclick = async function () {
                channelParameters.localAudioTrack.close();
                channelParameters.localVideoTrack.close();
                removeVideoDiv(remotePlayerContainer.id);
                removeVideoDiv(localPlayerContainer.id);
                await agoraEngine.leave();
                window.location.reload();
            }
        }
    }
    startBasicCall();
    function removeVideoDiv(elementId) {
        let Div = document.getElementById(elementId);
        if (Div) {
            Div.remove();
        }
    };
</script>
@endsection