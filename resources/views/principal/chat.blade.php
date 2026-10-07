@extends('layouts.master')

@section('title')
{{ __('principal') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('principal') }}
        </h3>
    </div>

</div>
@endsection

@section('scripts')
<script>
    // Retrieve Firebase Messaging object.
    const messaging = firebase.messaging();
    // Add the public key generated from the console here.
    messaging.usePublicVapidKey("your publicVapidKey");
</script>
@endsection

@section('scripts')
<script>
    // Retrieve Firebase Messaging object.
    const messaging = firebase.messaging();
    // Add the public key generated from the console here.
    messaging.usePublicVapidKey("BNi8WFY0HVBE2YvPXCfj7wDGbURGAh6rD57klttmetVL-PDYCnPNOgvUfC_RZquDF6uXjS5f78PXAcnFI9KuNTE");


    function sendTokenToServer(fcm_token) {
        const user_id = '{{auth()->user()->id}}';
        //console.log($user_id);
        axios.post('/api/save-token', {
            fcm_token, user_id
        })
            .then(res => {
                console.log(res);
            })

    }

    function retreiveToken(){
        messaging.getToken().then((currentToken) => {
            if (currentToken) {
                sendTokenToServer(currentToken);
                // updateUIForPushEnabled(currentToken);
            } else {
                // Show permission request.
                //console.log('No Instance ID token available. Request permission to generate one.');
                // Show permission UI.
                //updateUIForPushPermissionRequired();
                //etTokenSentToServer(false);
                alert('You should allow notification!');
            }
        }).catch((err) => {
            console.log(err.message);
            // showToken('Error retrieving Instance ID token. ', err);
            // setTokenSentToServer(false);
        });
    }
    retreiveToken();
    messaging.onTokenRefresh(()=>{
        retreiveToken();


    });

    messaging.onMessage((payload)=>{
        console.log('Message received');
        console.log(payload);

        location.reload();
    });

</script>
@endsection