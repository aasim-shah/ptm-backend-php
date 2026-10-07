@extends('layouts.master')

@section('title')
{{ __('promote_student') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('promote_students_in_next_session')}}
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    {{-- <h4 class="card-title">
                        {{ __('pro') . ' ' . __('promote_student') }}
                    </h4> --}}
                    <form action="{{ route('promote-student.store') }}" class="create-form" id="formdata">
                        @csrf
                        <div class="row" id="toolbar">
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('class') }} {{ __('section') }} <span class="text-danger">*</span></label>
                                <select required name="class_section_id" id="student_class_section"
                                class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                <option value="">{{ __('select') . ' ' . __('class') }}</option>
                                @foreach ($class_sections as $section)
                                <option value="{{ $section->id }}" data-class="{{ $section->class->id }}">
                                    {{ $section->class->name }} - {{ $section->section->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                <label>{{ __('promote_in') }} <span class="text-danger">*</span></label>
                                <select required name="session_year_id" id="session_year_id"
                                class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                <option value="">{{ __('select') . ' ' . __('session_years') }}</option>
                                @foreach ($session_year as $years)
                                <option value="{{ $years->id }}">{{ $years->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-12 col-md-4">
                            <label>{{ __('promote_class') }} <span class="text-danger">*</span></label>
                            <select required name="new_class_section_id" id="new_student_class_section"
                            class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                            <option value="">{{ __('select') . ' ' . __('class') }}</option>
                            @foreach ($class_sections as $section)
                            <option value="{{ $section->id }}" data-class="{{ $section->class->id }}">
                                {{ $section->class->name }} - {{ $section->section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>


                    <table aria-describedby="mydesc" class='table promote_student_table' id='promote_student_table_list'
                    data-toggle="table" data-url="{{ url('promote-student-list') }}"
                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                    data-page-list="[5, 10, 20, 50, 100, 200]"  data-search="true" data-toolbar="#toolbar"
                    data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                    data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                    data-maintain-selected="true" data-export-types='["txt","excel"]'
                    data-export-options='{ "fileName": "promote-student-list-<?= date('d-m-y') ?>" ,"ignoreColumn": ["operate"]}'
                    data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                {{ __('id') }}</th>
                                <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>

                                <th scope="col" data-field="student_id" data-sortable="true">
                                    {{ __('student_id') }}</th>

                                    <th scope="col" data-field="name" data-sortable="false">{{ __('name') }}
                                    </th>
                                    <th scope="col" data-field="result" data-sortable="false">{{ __('result') }}
                                    </th>
                                    <th scope="col" data-field="status" data-sortable="false">{{ __('status') }}
                                    </th>
                                </tr>
                            </thead>
                        </table>
                        <input class="btn btn-theme btn_promote" id="create-btn" type="submit" value={{ __('submit') }}>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    function queryParams(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            search: p.search,
            'class_section_id': $('#student_class_section').val(),
            'session_year_id': $('#session_year_id').val(),
        };
    }
</script>

<script>
    $('#student_class_section').on('change', function() {
        $('#promote_student_table_list').bootstrapTable('refresh');
    });
    // $('#session_year_id').on('change', function() {
        //     $('#promote_student_table_list').bootstrapTable('refresh');
        // });
        $('.btn_promote').hide();
        function set_data(){
            $(document).ready(function()
            {
                student_class=$('#student_class_section').val();
                session_year=$('#session_year_id').val();
                promote_class=$('#new_student_class_section').val();

                if(student_class!='' && session_year!='' && promote_class!='')
                {
                    $('.btn_promote').show();
                }
                else{
                    $('.btn_promote').hide();
                }
            });
        }
        $('#student_class_section,#session_year_id,#new_student_class_section').on('change', function() {
            set_data();
        });
    </script>
    @endsection
