@extends('layouts.master')

@section('title')
{{ __('online').' '.__('exam').' '.__('result') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('online').' '.__('exam').' '.__('result') }}
        </h3>
        <a class="btn btn-sm btn-theme" href="{{ route('online-exam.index') }}">{{ __('back') }}</a>
    </div>
    <div class="row grid-margin">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <input type="hidden" name="online_exam_id" value="{{$online_exam->id}}">
                        @if($online_exam->model_type == 'App\Models\ClassSection')
                            <div class="form-group col-md-4">
                                <label>{{ __('class').' '.__('name') }}</label>
                                <input type="text" id="result-class-name" value="{{$class_data->class->name.' - '.$class_data->section->name.' '.$class_data->class->medium->name}}" placeholder="{{ __('class').' '.__('name') }}" class="form-control" readonly />
                            </div>
                        @else
                            <div class="form-group col-md-4">
                                <label>{{ __('class').' '.__('name') }}</label>
                                <input type="text" id="result-class-name" value="{{$class_data->name.' '.$class_data->medium->name}}" placeholder="{{ __('class').' '.__('name') }}" class="form-control" readonly />
                            </div>
                        @endif
                        <div class="form-group col-md-4">
                            <label>{{ __('subject') }}</label>
                            <input type="text" id="result-subject-name" value="{{$online_exam->subject->name.' - '.$online_exam->subject->type}}" placeholder="{{ __('subject') }}" class="form-control" readonly />
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ __('online').' '.__('exam').' '.__('title') }}</label>
                            <input type="text" id="online-exam-title" value="{{$online_exam->title}}" placeholder="{{ __('title') }}" class="form-control" readonly />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table" data-url="{{ route('online-exam.result.show',$online_exam->id) }}" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-export-types='["txt","excel"]' data-export-options='{ "fileName": "{{__('online').' '.__('exam')}}-<?= date(' d-m-y') ?>" ,"ignoreColumn":["operate"]}' data-show-export="true" data-query-params="onlineExamResultQueryParams">
                        <thead>
                            <tr>
                                <th scope="col" data-field="student_id" data-sortable="true" data-visible="false">{{ __('student_id') }}</th>
                                <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                <th scope="col" data-field="student_name" data-sortable="false">{{ __('student_name')}}</th>
                                <th scope="col" data-field="marks" data-sortable="false">{{ __('marks') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
