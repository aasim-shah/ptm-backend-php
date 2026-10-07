@extends('layouts.master')

@section('title')
    {{ __('class_timetable') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage').' '.__('class_timetable') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{-- check for admin persmission --}}
                            @can('timetable-create')
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-6">
                                    <label>{{ __('class') }} {{ __('section') }} <span class="text-danger">*</span></label>
                                    <select required name="class_section_id" id="class_timetable_class_section" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                        <option value="">{{__('select')}}</option>
                                        @foreach($class_sections as $section)
                                            <option value="{{$section->id}}" data-class="{{$section->class->id}}" data-section="{{$section->section->id}}">{{$section->class->name.' '.$section->section->name.' - '.$section->class->medium->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endcan
                        </h4>
                        <div class="alert alert-warning text-center w-75 m-auto warning_no_data" role="alert" style="display: none">
                            <strong>{{__('no_data_found')}}</strong>
                        </div>

                        {{-- if teacher data exists --}}
                        @if(isset($teacher_data))
                        @if(sizeof($teacher_data['timetable']))
                        <div class="row">
                            @for ($i = 0; $i < count($teacher_data['days']); $i++)
                                <div class="col-lg-2 col-md-2 col-sm-2 col-12 project-grid">
                                    <div class="project-grid-inner">
                                        <div class="wrapper bg-light">
                                            <h5 class="card-header header-sm bg-secondary">{{ucfirst($teacher_data['days'][$i]['day_name'])}} </h5>
                                            @for ($j = 0; $j < count($teacher_data['timetable']) ; $j++)
                                                @if ($teacher_data['days'][$i]['day'] == $teacher_data['timetable'][$j]['day'])
                                                    <p class="card-body">
                                                        {{$teacher_data['timetable'][$j]['subject_teacher']['subject']['name'].' - '.$teacher_data['timetable'][$j]['subject_teacher']['subject']['type']}}
                                                        <br>{{$teacher_data['timetable'][$j]['subject_teacher']['teacher']['user']['first_name'] . ' ' .$teacher_data['timetable'][$j]['subject_teacher']['teacher']['user']['last_name']}}
                                                        <br>{{__('start_time').':'. $teacher_data['timetable'][$j]['start_time']}} <br> {{__('end_time').':'. $teacher_data['timetable'][$j]['end_time']}}
                                                    </p>
                                                    <hr>
                                                @endif
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        </div>
                        @else
                        <div class="alert alert-warning text-center w-75 m-auto warning_no_data" role="alert">
                            <strong>{{__('no_data_found')}}</strong>
                        </div>
                        @endif

                        {{-- if teacher data doesn't exists then the html wiil come from javascript --}}
                        @else
                        <div class="row set_timetable"></div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
