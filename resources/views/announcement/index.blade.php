<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
@extends('layouts.master')

@section('title')
{{ __('announcement') }}
@endsection
<style>
    .description{
        padding-bottom: 0 !important;
        overflow: hidden;
        border-bottom: 0;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }
    .table-bordered td {
        border-bottom: 0px !important;
    }

</style>

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage'). ' '.__('announcement') }}
        </h3>

    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('create'). ' '.__('announcement') }}
                    </h4>
                    <form class="create-form pt-3" action="{{ url('announcement') }}" id="formdata" method="POST"
                          novalidate="novalidate">
                        @csrf
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('title') }} <span class="text-danger">*</span></label>
                                {!! Form::text('title', null, [ 'placeholder' => __('title'), 'class' =>
                                'form-control']) !!}
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('description') }}<span class="text-danger">*</span></label>
                                {!! Form::textarea('description', null, ['rows' => '2', 'placeholder' =>
                                __('description'), 'class' => 'form-control']) !!}
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('files') }} </label>
                                <input type="file" name="file[]" class="form-control" multiple/>
                            </div>
                        </div>


                        <div class="row">
                            <div class="form-group col-sm-12 col-md-3">
                                <label>{{ __('assign_to') }}</label><span class="text-danger"> *</span>
                                <select name="set_data" id="set_data" class="form-control select2">
                                    <option value="">{{ __('select'). ' '.__('assign_to') }}</option>
                                    @if(Auth::user()->hasRole('Teacher'))
                                    <option value="class_section">{{ __('class'). ' '.__('section') }}</option>
                                    @else
                                    {{--
                                    <option value="class">{{ __('class') }}</option>
                                    --}}
                                    <option value="general">{{ __('noticeboard') }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-3 show_class_section_id">
                                <label>&nbsp;</label>
                                <select name="class_section_id" id="class_section_id"
                                        class="class_section_id form-control" style="width:100%;" tabindex="-1"
                                        aria-hidden="true">
                                    <option value="">{{ __('select'). ' '.__('class_section') }}</option>
                                    @foreach ($class_section as $item)
                                    <option value="{{ $item->id }}" data-class="{{ $item->class->id }}">
                                        {{ $item->class->name. ' '.$item->section->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-3 show_school_id">
                                <label>&nbsp;</label>
                                <select name="school_id" id="school_id" class="school_id form-control"
                                        style="width:100%;" tabindex="-1" aria-hidden="true">
                                    @hasrole(['Super Admin'])
                                    <option value="" >Select School</option>
                                    <option value="ALL_SCHOOL">All Schools</option>
                                    @endrole

                                    @foreach ($schools as $school)
                                    <option  value="{{ $school->id }}">
                                        {{ $school->school_name. ' '.$school->address }}
                                    </option>
                                    @endforeach
                                </select>

                            </div>
                            <div class="form-group col-sm-12 col-md-3 show_school_id">
                                <label>&nbsp;</label>
                                <select name="to_user" id="to_user" class="to_user form-control" style="width:100%;"
                                        tabindex="-1" aria-hidden="true">
                                    <option value="">Select User Type</option>
                                    <option value="ALL">All Users</option>
                                    <option value="teacher">Teachers</option>
                                    <option value="parent">Parents</option>
                                </select>

                            </div>
                            <div class="form-group col-sm-12 col-md-3 show_class_section_id">
                                <label>&nbsp;</label>
                                <select name="get_data[]" id="get_data" class="subject_id form-control"
                                        style="width:100%; display: none"></select>
                            </div>
                        </div>
                        <input class="btn theme-green" type="submit" value={{ __('submit') }}>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">

                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('list'). ' '.__('announcement') }}
                    </h4>
                    <div class="row">
                        <div class="col-9"></div>
                        <div class="col-3">
                            <button onclick="exportData('announcement',null)" class="btn btn-success float-right"><i
                                        class="fa fa-file-excel-o"></i>&nbsp;Export
                            </button>
                        </div>
                    </div>
                    <br>
                    <div class="row">

                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                   data-url="{{ url('announcement-list') }}" data-click-to-select="true"
                                   data-side-pagination="server" data-pagination="true"
                                   data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                   data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                                   data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1"
                                   data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                                   data-sort-order="asc" data-maintain-selected="true"
                                   data-export-types='["txt","excel"]'
                                   data-export-options='{ "fileName": "announcement-list-<?= date('d-m-y') ?>" ,"ignoreColumn": ["operate"]}'
                                   data-query-params="queryParams">
                                <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}
                                    </th>
                                    <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                    <th scope="col" data-field="title" data-sortable="false">{{ __('title') }}</th>
                                    <th scope="col" class="description" data-field="description" data-sortable="false">{{ __('description')
                                        }}
                                    </th>
                                    @hasrole(['Super Admin'])
                                    <th scope="col" data-field="school" data-sortable="false">{{ __('School')}}
                                    </th>
                                    @endrole
                                    <th scope="col" data-field="file" data-sortable="false"
                                        data-formatter="fileFormatter">{{ __('files') }}
                                    </th>
                                    <th data-events="announcementEvents" data-width="150" scope="col"
                                        data-field="operate" data-sortable="false">{{ __('action') }}
                                    </th>
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


<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> {{ __('edit'). ' '.__('announcement') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true"><i class="fa fa-close"></i></span>
                </button>
            </div>
            <form id="formdata" class="editform" action="{{ url('announcement') }}" novalidate="novalidate">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id" id="id">
                    <div class="row">
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('title') }} <span class="text-danger">*</span></label>
                            {!! Form::text('title', null, ['required', 'placeholder' => __('title'), 'class' =>
                            'form-control', 'id' => 'title']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-12">
                            <label>{{ __('description') }}</label>
                            {!! Form::textarea('description', null, ['rows' => 2, 'placeholder' => __('description'),
                            'class' => 'form-control', 'id' => 'description']) !!}
                        </div>
                        <div class="form-group col-sm-12 col-md-12 edit_show_class_section_id">
                            <label>&nbsp;</label>

                            <select name="class_section_id" id="edit_class_section_id"
                                    class="form-control js-example-basic-single select2-hidden-accessible"
                                    style="width:100%;" tabindex="-1" aria-hidden="true">
                                <option value="">{{ __('select'). ' '.__('class_section') }}</option>
                                @foreach ($class_section as $item)
                                <option value="{{ $item->id }}" data-class="{{ $item->class->id }}">
                                    {{ $item->class->name. ' '.$item->section->name }}
                                </option>
                                @endforeach
                            </select>
                            <br>
                            <br>
                            <div class="form-group">
                                <label>{{ __('old_files') }} </label>
                                <div id="old_files"></div>
                            </div>

                            <div class="form-group">
                                <label>{{ __('upload_new_files') }} </label>
                                <input type="file" name="file[]" class="form-control" multiple/>
                            </div>
                        </div>
                        <div class="form-group col-sm-12 col-md-12 edit_show_class_section_id">
                            <label>&nbsp;</label>
                            <select name="class_section_id" id="edit_class_section_id"
                                    class="form-control js-example-basic-single select2-hidden-accessible"
                                    style="width:100%;" tabindex="-1" aria-hidden="true">
                                <option value="">{{ __('select'). ' '.__('class_section') }}</option>
                                @foreach ($class_section as $item)
                                <option value="{{ $item->id }}" data-class="{{ $item->class->id }}">
                                    {{ $item->class->name. ' '.$item->section->name }}
                                </option>
                                @endforeach
                            </select>
                            <br>
                            <br>
                            <div class="form-group">
                                <label>{{ __('upload_new_files') }} </label>
                                <input type="file" name="file[]" class="form-control" multiple/>
                            </div>
                        </div>
                        <div class="form-group col-sm-12 col-md-12 edit_show_class_section_id">
                            <label>&nbsp;</label>
                            <select name="get_data" id="edit_get_data" class="form-control" style="width:100%;"
                                    tabindex="-1" aria-hidden="true"></select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input class="btn btn-theme" type="submit" value={{ __('submit') }}>
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ __('cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    $('.show_class_section_id').hide();

    $('#set_data').on('change', function () {
        data = $(this).val();
        if (data == 'class_section') {
            $('.show_class_section_id').show();
            $('#get_data').show();
        } else {
            $('.show_class_section_id').hide();
            $('#get_data').hide();
        }
        $.ajax({
            url: "{{ url('getAssignData') }}",
            type: "GET",
            data: {
                data: data
            },
            success: function (response) {
                html = '';
                if (data == 'class') {
                    for (let i = 0; i < response.length; i++) {
                        html += '<option value=' + response[i]['id'] + '>' + response[i]['name'] +
                            '</option>';
                    }
                }
                $('#get_data').html(html);
            }
        });
    });

</script>

<script>
    $('.edit_show_class_section_id').hide();
    $('#edit_set_data').on('change', function (e, type_id) {
        data = $(this).val();
        if (data == 'class_section') {
            $('.edit_show_class_section_id').show();
        } else {
            $('.edit_show_class_section_id').hide();
        }
        $.ajax({
            url: "{{ url('getAssignData') }}",
            type: "GET",
            data: {
                data: data
            },
            success: function (response) {
                html = '';
                if (data == 'class') {
                    for (let i = 0; i < response.length; i++) {
                        var chk = (response[i]['id'] == type_id) ? 'selected' : '';
                        html += '<option value=' + response[i]['id'] + '' + chk + '>' + response[i][
                                'name'
                                ] +
                            '</option>';
                    }
                }
                $('#edit_get_data').html(html);
            }
        });
    });


    $('#edit_class_section_id').on('change', function (e, subjectid) {
        data = $('#edit_set_data').val();
        class_id = $('#edit_class_section_id').find(':selected').attr('data-class');

        $.ajax({
            url: "{{ url('getAssignData') }}",
            type: "GET",
            data: {
                data: data,
                class_id: class_id
            },
            success: function (response) {
                html = '';
                if (response != '') {
                    html += '<option value="">Select Subject</option>';
                    for (let i = 0; i < response.length; i++) {
                        var chk = (response[i]['subject']['id'] == subjectid) ? 'selected' : '';
                        html += '<option value=' + response[i]['subject']['id'] + ' ' + chk + '>' +
                            response[i]['subject']['name'] + '</option>';

                    }
                }
                $('#edit_get_data').html(html);
            }
        });
    });
</script>
<script type="text/javascript">
    function queryParams(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            search: p.search
        };
    }
</script>
@endsection
