@extends('layouts.master')

@section('title')
    {{ __('assign_new_student_class') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('assign_new_student_class') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('students.assign-class.store') }}" class="assign_student_class"
                            id="formdata">
                            @csrf
                            <div class="row" id="toolbar">
                                <div class="form-group col-sm-12 col-md-6">
                                    <select required name="class_id" id="class_id" class="form-control select2"
                                        style="width:100%;" tabindex="-1" aria-hidden="true">
                                        <option value="">{{ __('class') }}</option>
                                        @foreach ($class as $class)
                                            <option value="{{ $class->id }}">
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-6">
                                    <select required name="class_section_id" class="form-control select2"
                                        id="class_section_id" style="width:100%;" tabindex="-1" aria-hidden="true">
                                        <option value="">{{ __('class_section') }}</option>
                                        @foreach ($class_section as $class_section)
                                            <option value="{{ $class_section->id }}">
                                                {{ $class_section->class->name . ' - ' . $class_section->section->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <textarea readonly hidden name="selected_id" id="all_id"></textarea>
                            </div>
                            <div class="assign_student_show">
                                <table aria-describedby="mydesc" class='table' id='assign_table_list' data-toggle="table"
                                    data-url="{{ route('students.new-student-list') }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                    data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                    data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                    data-maintain-selected="true" data-export-types='["txt","excel"]'
                                    data-export-options='{ "fileName": "new-students-list-<?= date('d-m-y') ?>"
                                    ,"ignoreColumn": ["operate"]}'
                                    data-query-params="queryParams">
                                    <thead>
                                        <tr>
                                            <th scope="col" data-field="chk" data-sortable="false">#</th>
                                            <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                                {{ __('id') }}</th>
                                            <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}
                                            </th>
                                            <th scope="col" data-field="user_id" data-sortable="false"
                                                data-visible="false">
                                                {{ __('user_id') }}</th>
                                            <th scope="col" data-field="first_name" data-sortable="false">
                                                {{ __('first_name') }}
                                            </th>
                                            <th scope="col" data-field="last_name" data-sortable="false">
                                                {{ __('last_name') }}
                                            </th>
                                            <th scope="col" data-field="image" data-sortable="false"
                                                data-formatter="imageFormatter">{{ __('image') }}</th>
                                            <th scope="col" data-field="class_section_name" data-sortable="false">
                                                {{ __('class') . ' ' . __('section') }}</th>
                                            <th scope="col" data-field="admission_no" data-sortable="false">
                                                {{ __('admission_no') }}</th>
                                            <th scope="col" data-field="roll_number" data-sortable="false">
                                                {{ __('roll_no') }}
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                            <input class="btn btn-theme" id="btn_assign" type="submit" value={{ __('submit') }}>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@section('js')
    <script>
        function queryParams(p) {
            return {
                limit: p.limit,
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                search: p.search,
                'class_id': $('#class_id').val(),
            };
        }
    </script>
    <script>
        $('#class_id').on('change', function() {
            $('#assign_table_list').bootstrapTable('refresh');
        });
    </script>
    <script type="text/javascript">
        selected_student = [];
        $('#btn_assign').hide();
        $(document).on('click', '.assign_student', function(e) {
            if (this.checked == true) {
                selected_student.push($(this).val());

            } else {

                var index = selected_student.indexOf($(this).val());
                if (index > -1) {
                    selected_student.splice(index, 1);
                }
            }
            $('#all_id').val(selected_student);
            if ($('#all_id').val() != '') {
                $('#btn_assign').show();
            } else {
                $('#btn_assign').hide();
            }
        });
    </script>
@endsection
