@extends('layouts.master')

@section('title')
{{ __('exam_marks') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('exam_marks') }}
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('create') . ' ' . __('exam_marks') }}
                    </h4>
                    <form action="{{ route('exams.submit-marks') }}" class="create-form" id="formdata">
                        @csrf
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                @if(isset($exams))
                                <select required name="exam_id" id="exam_id" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">{{ __('select') . ' ' . __('exam') }}</option>
                                    @foreach ($exams as $data)
                                    <option value="{{ $data->id }}" data-class="{{$class_id}}"> {{ $data->name }}</option>
                                    @endforeach
                                </select>
                                @else
                                <select required name="exam_id" id="exam_id" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">--- No Exams ---</option>
                                </select>
                                @endif
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <select required name="subject_id" id="exam_subject_id" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">{{ __('select_subject') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-12">
                                <button type="button" id="search" class="btn btn-theme">Search</button>
                            </div>
                        </div>
                        <div class="show_student_list">
                            <table aria-describedby="mydesc" class='table student_table' id='table_list' data-toggle="table" data-url="{{ route('exams.marks-list') }}" data-click-to-select="true" data-side-pagination="server" data-pagination="false" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-export-types='["txt","excel"]' data-export-options='{ "fileName": "exam-result-list-<?= date(' d-m-y') ?>" ,"ignoreColumn": ["operate"]}'
                                data-query-params="uploadMarksqueryParams" data-toolbar="#toolbar">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                        <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                        <th scope="col" data-field="student_name" data-sortable="true" data-formatter="examStudentNameFormatter">{{ __('name') }}</th>
                                        <th scope="col" data-field="total_marks" data-sortable="false">{{ __('total_marks') }}</th>
                                        <th scope="col" data-field="obtained_marks" data-sortable="false" data-formatter="obtainedMarksFormatter">{{ __('obtained_marks') }}</th>
                                        {{-- <th scope="col" data-field="teacher_review" data-sortable="false" data-formatter="teacherReviewFormatter">{{ __('teacher_review') }}</th> --}}
                                    </tr>
                                </thead>
                            </table>
                            <input class="btn btn-theme mt-4" id="create-btn-result" type="submit" value={{ __('submit') }}>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $('#search').on('click , input', function () {
        $('.show_student_list').show();
        $('.student_table').bootstrapTable('refresh');
    });
    $('#table_list').on('load-success.bs.table', function (e, response ) {
        if(response.error == true){
            showErrorToast(response.message);
            $('.show_student_list').hide();
        }
    })

</script>
@endsection
