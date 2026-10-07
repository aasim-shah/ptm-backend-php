@php
    $var = getSettings('update_warning_modal')['update_warning_modal'] ?? 0;
@endphp
<div class="show-update-note-modal">
    @if((getSettings('system_version')['system_version'] ?? null) == '1.0.7' && $var != 1)
        <!-- Button trigger modal -->
        <button type="button" class="btn btn-primary d-none" id="UpdateDetailsModalButton" data-toggle="modal" data-target="#UpdateDetails">
            Launch static backdrop modal
        </button>

        <!-- Modal -->
        <div class="modal fade" id="UpdateDetails" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title" id="UpdateDetailsLabel">PLEASE READ CAREFULLY</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                </div>
                <div class="modal-body">
                    <ul>
                        <li>Update the detials of Fees Class's Fees Choiceable Data by clicking here <br><a class="btn btn-theme btn-sm" href="{{route('fees.class.index')}}">Click Here</a></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-theme" id="confirmed-modal">Understood</button>
                </div>
            </div>
            </div>
        </div>
        <script>
            $('#UpdateDetailsModalButton').click();
            $('#confirmed-modal').click(function(e){
                e.preventDefault();
                $.ajax({
                    type: "get",
                    url: "{{route('update-warning-modal')}}",
                    success: function (response) {
                        $('#UpdateDetails').modal("hide");
                    }
                });
            })
        </script>
    @endif
</div>
