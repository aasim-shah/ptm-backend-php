<div class="repeater">
    <div class="row">
        <div class="input-group col-sm-12 col-md-2">
            Subjects <span class="text-danger"> *</span>
        </div>
        <div class="input-group col-sm-12 col-md-2">
            Teacher <span class="text-danger">*</span>
        </div>
        <div class="input-group col-sm-12 col-md-2">
            Start time <span class="text-danger">*</span>
        </div>
        <div class="input-group col-sm-12 col-md-2">
            End time <span class="text-danger">*</span>
        </div>
        <div class="input-group col-sm-12 col-md-2">
            Note
        </div>
        <div class="input-group col-sm-12 col-md-2">
            <button data-repeater-create type="button" class="addmore d-none btn btn-gradient-info btn-sm icon-btn ml-2 mb-2">
                <i class="fa fa-plus"></i>
            </button>
        </div>
    </div>

    <form class="pt-3" action="{{url('timetable')}}" id="formdata" method="POST" novalidate="novalidate">
        @csrf
        <input required type="hidden" name="day" id="day" class="day">
        <input required type="hidden" name="class_section_id" id="class_section_id">
