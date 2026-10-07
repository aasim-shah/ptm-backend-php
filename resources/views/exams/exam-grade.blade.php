@extends('layouts.master')

@section('title')
{{ __('manage'). ' ' . __('grade')}}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('grade')}}
        </h3>
    </div>

    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <h4 class="page-title mb-4">
                        {{ __('create') . ' ' . __('grade')}}
                    </h4>
                    <div class="form-group">
                        {{-- Template for New Grade --}}
                        <div class="grade_content_div" style="display: none;">
                            <div class="grade_content">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>{{ __('starting_range') }} </label>
                                        <input type="number" name="grade[0][starting_range]" class="temp_starting_range form-control" placeholder="{{ __('starting_range') }}" required />
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('ending_range') }} </label>
                                        <input type="number" name="grade[0][ending_range]" class="temp_ending_range form-control" placeholder="{{ __('ending_range') }}" required />
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>{{ __('grade') }} </label>
                                        <input type="text" name="grade[0][grades]" class="temp_grade form-control" placeholder="{{ __('grade') }}" required />
                                    </div>
                                    <div class="form-group col-md-1 pl-0 mt-4">
                                        <button type="button" class="btn btn-icon btn-inverse-danger remove-grades" title="Remove Grade">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{--End Template for New Grade --}}
                        <form id="create-grades" action="{{ url('create-grades') }}" method="POST">
                            <div class="extra_content">
                                @for ($i = 0; $i < count($grades); $i++) <div class="grade_content">
                                    <div class="row">
                                        <input type="hidden" name="grade[{{$i}}][id]" class="form-control hidden" value={{$grades[$i]['id']}} />
                                        <div class="form-group col-md-4">
                                            <label>{{ __('starting_range') }} </label>
                                            @if(isset($grades[$i-1]))
                                            @php
                                            $min = $grades[$i-1]['ending_range'];
                                            $min = $min+1;
                                            @endphp
                                            <input type="number" min="{{$min}}" name="grade[{{$i}}][starting_range]" class="starting_range form-control" placeholder="{{ __('starting_range') }}" value="{{$grades[$i]['starting_range']}}" />
                                            @else
                                            <input type="number" min="0" name="grade[{{$i}}][starting_range]" class="starting_range form-control" placeholder="{{ __('starting_range') }}" value="{{$grades[$i]['starting_range']}}" />
                                            @endif
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>{{ __('ending_range') }} </label>
                                            @if(isset($grades[$i+1]))
                                            @php
                                            $max = $grades[$i+1]['starting_range'];
                                            $max = $max-1;
                                            @endphp
                                            <input type="number" name="grade[{{$i}}][ending_range]" max="{{$max}}" class="ending_range form-control" placeholder="{{ __('ending_range') }}" value="{{$grades[$i]['ending_range']}}" />
                                            @else
                                            <input type="number" name="grade[{{$i}}][ending_range]" max=100 class="ending_range form-control" placeholder="{{ __('ending_range') }}" value="{{$grades[$i]['ending_range']}}" />
                                            @endif
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>{{ __('grade') }} </label>
                                            <input type="text" name="grade[{{$i}}][grades]" class="grade form-control" placeholder="{{ __('grade') }}" value="{{$grades[$i]['grade']}}" />
                                        </div>
                                        <div class="form-group col-md-1 pl-0 mt-4">
                                            <button type="button" class="btn btn-icon btn-inverse-danger remove-grades" data-id="{{$grades[$i]['id']}}" title="Remove Grade">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                            </div>
                            @endfor
                    </div>
                    <div class="extra-grade-content"></div>
                    <div class="col-md-4 pl-0 mb-4">
                        <button type="button" class="btn btn-success add-grade-content" title="Add new row">
                            Add New Data
                        </button>
                    </div>
                    <input type="submit" class="btn btn-theme" value={{ __('submit') }} />
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
