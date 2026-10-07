@extends('layouts.master')
@section('title')
{{__('dashboard')}}
@section('content')

<div class="content-wrapper">
    <!--    <div class="page-header">-->
    <!---->
    <!--    </div>-->
    <div class="container" style="height: 400px">
        <div class="row justify-content-center h-100">
            <div class="col-md-4 h-100">
                <div class="card h-100 ">
                    <div class="card-header">
                        History
                    </div>
                </div>
                <form method="post">
                    @csrf
                    <input type="text" name="message" id="message" placeholder="Type your message">
                    <button type="button" onclick="sendMessages()">Send</button>
                </form>
            </div>
        </div>
    </div>
</div>


@section('script')
<script>
    function sendMessages() {
        var formData = new FormData();
        const message = document.getElementById("message").value;
        formData.append("message", message);

        axios.post('/send-message2', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(res => {
            // window.location.reload()


        })
    }
</script>
@endsection

</html>