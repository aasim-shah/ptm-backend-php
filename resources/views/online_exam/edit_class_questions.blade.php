@extends('layouts.master')

@section('title')
{{ __('manage').' '.__('online').' '.__('exam').' '.__('questions') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('add_online_exam_questions') }}
        </h3>
    </div>
    <div class="row grid-margin">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form class="pt-3 mt-6" id="edit-online-exam-question-form" method="POST" action="{{ route('online-exam-question.index') }}">
                        <input type="hidden" name="edit_id" class="edit_id">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>{{ __('class') }} <span class="text-danger">*</span></label>
                                <select required name="edit_class_id" value="{{$questions_options->class_subject->class_id}}" class="form-control select2 online-exam-class-id" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">--- {{ __('select') . ' ' . __('class') }} ---</option>
                                    @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">
                                        {{ $class->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>{{ __('subject') }} <span class="text-danger">*</span></label>
                                <select required name="edit_subject_id" class="form-control select2 online-exam-subject-id" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">--- {{ __('select') . ' ' . __('subject') }} ---</option>
                                </select>
                            </div>
                        </div>
                        {{-- <div class="bg-light p-4">
                            <div class="form-group">
                                <label>{{ __('question_type') }} <span class="text-danger">*</span></label><br>
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
                                    <textarea id="equation-editor-question" name="equestion" required placeholder="{{__('enter').' '.__('question')}}"></textarea>
                                </div>
                                <div class="row equation-option-container p-4">
                                    <div class="form-group col-md-6">
                                        <label>{{ __('option') }} <span class="option-number">1</span> <span class="text-danger">*</span></label>
                                        <textarea id="eoption-1" name="eoption[1]" required placeholder="{{__('enter').' '.__('option')}}"></textarea>
                                    </div>
                                    <div class="form-group col-md-6 quation-option-template">
                                        <label>{{ __('option') }} <span class="equation-option-number">2</span> <span class="text-danger">*</span></label>
                                        <textarea id="eoption-2" name="eoption[2]" required placeholder="{{__('enter').' '.__('option')}}"></textarea>
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
                        </div> --}}
                        <input class="btn btn-theme mt-4" id="new-question-add" type="submit" value={{__('submit')}}>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
