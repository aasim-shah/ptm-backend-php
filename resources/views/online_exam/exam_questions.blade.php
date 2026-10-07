@extends('layouts.master')

@section('title')
{{ __('assign').' '.__('questions') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('assign').' '.__('questions') }}
        </h3>
        <a class="btn btn-sm btn-theme" href="{{ route('online-exam.index') }}">{{ __('back') }}</a>
    </div>
    <div class="row grid-margin">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form class="pt-3 mt-6" id="add-new-question-online-exam" method="POST" action="{{ route('online-exam.add-new-question') }}">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>{{ __('class') }}</label>
                                <input type="text" id="add-question-class" value="{{$class_data['class_name']}}" placeholder="{{ __('class') }}" class="form-control" readonly />
                            </div>
                            <div class="form-group col-md-4">
                                <label>{{ __('subject') }}</label>
                                <input type="text" id="add-question-subject" value="{{$online_exam_db->subject->name.' - '.$online_exam_db->subject->type}}" placeholder="{{ __('subject') }}" class="form-control" readonly />
                            </div>
                            <input type="hidden" name="online_exam_id" value="{{$online_exam_db->id}}">
                            <div class="form-group col-md-4">
                                <label>{{ __('online') }} {{__('exam')}} {{__('title')}}</label>
                                <input type="text" value="{{$online_exam_db->title}}" placeholder="{{ __('title') }}" class="form-control" readonly />
                            </div>
                        </div>
                        <hr>
                        <div class="add-new-question-container" style="display:none">
                            <div class="bg-light p-4">
                                <div class="form-group">
                                    <label>{{ __('question_type') }} <span class="text-danger">*</span></label> <button type="button" class="btn btn-danger btn-sm d-flex float-right remove-add-new-question"><i class="fa fa-times-circle" aria-hidden="true"></i></button><br>
                                    <div class="d-flex">
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">
                                                <input type="radio" name="question_type" class="question_type" value="0" checked>
                                                {{ __('simple_question') }}
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">
                                                <input type="radio" name="question_type" class="question_type" value="1">
                                                {{ __('equation_based') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div id="simple-question">
                                    <div class="form-group">
                                        <label>{{ __('question') }} <span class="text-danger">*</span></label>
                                        {!! Form::textarea('question', null, ['placeholder' => __('enter').' '.__('question'), 'class' => 'form-control','rows'=>4]) !!}
                                    </div>
                                    <div class="row option-container">
                                        <div class="form-group col-md-6">
                                            <label>{{ __('option') }} <span class="option-number">1</span> <span class="text-danger">*</span></label>
                                            <input type="text" name="option[1]" placeholder="{{ __('enter').' '.__('option') }}" class="form-control add-question-option" />
                                        </div>
                                        <div class="form-group col-md-6 option-template">
                                            <label>{{ __('option') }} <span class="option-number">2</span> <span class="text-danger">*</span></label>
                                            <input type="text" name="option[2]" placeholder="{{ __('enter').' '.__('option') }}" class="form-control add-question-option" />
                                            <div class="remove-option-content"></div>
                                        </div>
                                    </div>
                                    <div class="add_button">
                                        <button class="btn btn-dark btn-sm" type="button" id="add-new-option"><i class="fa fa-plus-circle fa-3x mr-2" aria-hidden="true"></i>{{__('add_option')}}</button>
                                    </div>
                                </div>
                                <div id="equation-question" style="display: none">
                                    <div class="form-group col-md-12 col-sm-12">
                                        <label>{{ __('question') }} <span class="text-danger">*</span></label>
                                        <textarea class="editor_question" name="equestion" required placeholder="{{__('enter').' '.__('question')}}"></textarea>
                                    </div>
                                    <div class="row equation-option-container p-4">
                                        <div class="form-group col-md-6">
                                            <label>{{ __('option') }} <span class="option-number">1</span> <span class="text-danger">*</span></label>
                                            <textarea class="editor_options" name="eoption[1]" required placeholder="{{__('enter').' '.__('option')}}"></textarea>
                                        </div>
                                        <div class="form-group col-md-6 quation-option-template">
                                            <label>{{ __('option') }} <span class="equation-option-number">2</span> <span class="text-danger">*</span></label>
                                            <textarea class="editor_options" name="eoption[2]" required placeholder="{{__('enter').' '.__('option')}}"></textarea>
                                            <div class="remove-equation-option-content"></div>
                                        </div>
                                    </div>
                                    <div class="add_button_equations">
                                        <button class="btn btn-dark btn-sm" type="button" id="add-new-eqation-option"><i class="fa fa-plus-circle fa-3x mr-2" aria-hidden="true"></i>{{__('add_option')}}</button>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="form-group col-md-6 mt-2">
                                        <div class="form-group">
                                            <label>{{ __('answer') }} <span class="text-danger">*</span></label>
                                            <select multiple required name="answer[]" id="answer_select" class="form-control js-example-basic-single select2-hidden-accessible" style="width:100%;" tabindex="-1" aria-hidden="true">
                                                <option value="1">{{__('option')}} 1</option>
                                                <option value="2">{{__('option')}} 2</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>{{ __('image') }}</label>
                                        <input type="file" name="image" class="file-upload-default" />
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('image') }}" />
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button" >{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group p-1">
                                    <label>{{ __('note') }}</label>
                                    <input type="text" name="note" class="form-control">
                                </div>
                            </div>
                            <input class="btn btn-theme mt-4" id="new-question-add" type="submit" value={{__('add')}}>
                        </div>
                    </form>
                    <div class="row">
                        <button type="buttton" class="btn btn-theme ml-3 add-new-question-button">{{__('add_new_question')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('list').' '.__('questions') }}
                    </h4>

                    <table aria-describedby="mydesc" class='table table-striped' id='table_list_exam_questions' data-toggle="table" data-url="{{ route('online-exam-question.get-class-subject-questions',['online_exam_id' => $online_exam_db->id]) }}"   data-checkbox-header="false" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-show-columns="true" data-fixed-columns="true" data-trim-on-search="true" data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-query-params="onlineExamQuestionsQueryParams" data-show-refresh="true">
                        <thead>
                            <tr>
                                <th data-field="state" data-checkbox="true"></th>
                                <th scope="col" data-field="question_id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                <th scope="col" data-field="question" data-sortable="false">{{ __('question')}}</th>
                                <th scope="col" data-field="image" data-formatter="imageFormatter" data-sortable="false" data-align="center">{{ __('image') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('assign').' '.__('questions') }}
                    </h4>
                    <form class="pt-3 mt-6" id="store-assign-questions-form" method="POST" action="{{ route('online-exam.store-choice-question') }}">
                        <input type = "hidden" name="exam_id" value="{{$online_exam_db->id}}"/>
                        <div id='questions_block' class="form-group mt-4" style="overflow-y:scroll;height:700px;">
                            <ol id="sortable-row">
                                @if(isset($exam_questions) && !empty($exam_questions))
                                    @foreach ($exam_questions as $questions)
                                        @if($questions->questions->question_type)
                                            <div class="list-group">
                                                <input type="hidden" name="assign_questions[{{$questions->question_id}}][edit_id]" value="{{$questions->id}}">
                                                <input type="hidden" name="assign_questions[{{$questions->question_id}}][question_id]" value="{{$questions->question_id}}">
                                                <li id="q{{$questions->question_id}}"class="list-group-item d-flex justify-content-between align-items-center ui-state-default list-group-item-secondary m-2">
                                                    {{$questions->question_id}}
                                                    <div>
                                                        <textarea class="equation-editor-inline" name="qc{{$questions->question_id}}">{{htmlspecialchars_decode($questions->questions->question)}}</textarea>
                                                    </div>
                                                    <span class="text-right row">
                                                        <input type="number" class="list-group-item col-md-6" name="assign_questions[{{$questions->question_id}}][marks]" style="width: 10rem" value="{{$questions->marks}}" min="0">
                                                        <a class="btn btn-danger btn-sm remove-row ml-2" data-edit_id="{{$questions->id}}" data-id="{{$questions->question_id}}">
                                                            <i class="fa fa-times" aria-hidden="true"></i>
                                                        </a>
                                                    </span>
                                                </li>
                                            </div>
                                        @else
                                            <div class="list-group">
                                                <input type="hidden" name="assign_questions[{{$questions->question_id}}][edit_id]" value="{{$questions->id}}">
                                                <input type="hidden" name="assign_questions[{{$questions->question_id}}][question_id]" value="{{$questions->question_id}}">
                                                <li id="q{{$questions->question_id}}"class="list-group-item d-flex justify-content-between align-items-center ui-state-default list-group-item-secondary m-2">
                                                    {{$questions->question_id}} <span class="text-center">{{htmlspecialchars_decode($questions->questions->question)}}</span>
                                                    <span class="text-right row">
                                                        <input type="number" class="list-group-item col-md-6" name="assign_questions[{{$questions->question_id}}][marks]" style="width: 10rem" value="{{$questions->marks}}" min="0">
                                                        <a class="btn btn-danger btn-sm remove-row ml-2" data-edit_id="{{$questions->id}}" data-id="{{$questions->question_id}}">
                                                            <i class="fa fa-times" aria-hidden="true"></i>
                                                        </a>
                                                    </span>
                                                </li>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </ol>
                            <input class="btn btn-theme ml-4 submit_questions_btn" type="submit" value={{ __('submit') }} />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
