@extends('layouts.master')
@section('title')
{{__('dashboard')}}
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
            <span class="page-title-icon text-white mr-2 cloud-blue">
                <i class="fa fa-home"></i>
            </span> {{__('dashboard')}}
        </h3>
    </div>
    @hasrole(['Principal'])
    <div class="row">
        @if(isset($parent))
        <div class="col-md-4 stretch-card grid-margin p-1">
            <div class="card bg-gradient-warning card-img-holder text-white p-0">
                <div class="card-body p-0">
                    <table class="w-100">
                        <tr>
                            <td class="text-center">
                                <img src="{{asset(config('global.PARENTS_IMAGE')) }}" style="top: 50%;right: 20px" width="100px" height="100px" alt="circle-image"/>
                            </td>
                            <td>

                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_parents')}}<i class="mdi mdi-diamond mdi-24px float-right"></i>
                                </h4>
                                <h2 class="mb-5">{{$parent}}</h2>
                            </td>
                        </tr>
                    </table>
                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif
        @if(isset($teacher))
        <div class="col-md-4 stretch-card grid-margin p-1">
            <div class="card bg-gradient-inner-green card-img-holder text-white p-0">
                <div class="card-body p-0">

                    <table class="w-100">
                        <tr>
                            <td class="text-center">
                                <img src="{{asset(config('global.TEACHERS_IMAGE')) }}" style="top: 50%;right: 20px" width="100px" height="100px" alt="circle-image"/>
                            </td>
                            <td>

                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_teachers')}}<i class="mdi mdi-chart-line mdi-24px float-right"></i>
                                </h4>
                                <h2 class="mb-5">{{$teacher}}</h2>
                            </td>
                        </tr>
                    </table>

                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif

        @if(isset($student))
        <div class="col-md-4 stretch-card grid-margin p-1">
            <div class="card bg-gradient-inner-pink card-img-holder text-white p-0">
                <div class="card-body p-0">

                    <table class="w-100">
                        <tr>
                            <td class="text-center">
                                <img src="{{asset(config('global.STUDENTS_IMAGE')) }}" style="top: 50%;right: 20px" width="100px" height="100px" alt="circle-image"/>
                            </td>
                            <td>

                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_students')}}<i class="mdi mdi-bookmark-outline mdi-24px float-right"></i>
                                </h4>
                                <h2 class="mb-5">{{$student}}</h2>
                            </td>
                        </tr>
                    </table>
                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif

    </div>
    @endrole

    @hasrole(['Super Admin'])
    <div class="row">
        @if(isset($parent))
        <div class="col-md-3 stretch-card grid-margin p-1">
            <div class="card bg-gradient-inner-yellow card-img-holder text-white p-0">
                <div class="card-body p-0">

                    <table class="w-100">
                        <tr>
                            <td class="text-center"> <img src="{{asset(config('global.PARENTS_IMAGE')) }}" style="top: 50%;right: 20px" width="95px" height="95px" alt="circle-image"/>
                            </td>
                            <td>
                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_parents')}}
                                </h4>
                                <h2 class="mb-5">{{$parent}}</h2>
                            </td>
                        </tr>
                    </table>

                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif
        @if(isset($teacher))
        <div class="col-md-3 stretch-card grid-margin  p-1">
            <div class="card bg-gradient-danger card-img-holder text-white p-0">
                <div class="card-body p-0">
                    <table class="w-100">
                        <tr>
                            <td class="text-center">
                                <img src="{{asset(config('global.TEACHERS_IMAGE')) }}" style="top: 50%;right: 20px" width="95px" height="95px" alt="circle-image"/>
                            </td>
                            <td>

                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_teachers')}}<i class="mdi mdi-chart-line mdi-24px float-right"></i>
                                </h4>
                                <h2 class="mb-5">{{$teacher}}</h2>
                            </td>
                        </tr>
                    </table>
                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif

        @if(isset($student))
        <div class="col-md-3 stretch-card grid-margin  p-1">
            <div class="card bg-gradient-info card-img-holder text-white p-0">
                <div class="card-body p-0">
                    <table class="w-100">
                        <tr>
                            <td class="text-center">
                                <img src="{{asset(config('global.STUDENTS_IMAGE')) }}" style="top: 50%;right: 20px" width="95px" height="95px" alt="circle-image"/>
                            </td>
                            <td>

                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_students')}}<i class="mdi mdi-bookmark-outline mdi-24px float-right"></i>
                                </h4>
                                <h2 class="mb-5">{{$student}}</h2>
                            </td>
                        </tr>
                    </table>


                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif

        @if(isset($schoolsCount))
        <div class="col-md-3 stretch-card grid-margin  p-1">
            <div class="card bg-gradient-green card-img-holder text-white p-0">
                <div class="card-body p-0">

                    <table class="w-100">
                        <tr>
                            <td class="text-center">
                                <img src="{{asset(config('global.SCHOOLS_IMAGE')) }}" style="top: 50%;right: 20px" width="95px" height="95px" alt="circle-image"/>
                            </td>
                            <td>

                                <h4 class="font-weight-normal mb-2 mt-4">{{__('total_schools')}}<i class="mdi mdi-chart-line mdi-24px float-right"></i>
                                </h4>
                                <h2 class="mb-5">{{$schoolsCount}}</h2>
                            </td>
                        </tr>
                    </table>
                    <img src="{{asset(config('global.CIRCLE_SVG')) }}" class="card-img-absolute" alt="circle-image"/>

                </div>
            </div>
        </div>
        @endif

    </div>
    @endrole

    <div class="row">
        @if(isset($teachers) && !empty($teachers))
        <div class="col-md-7 grid-margin stretch-card">
            <div class="card">
                <div class="card-body v-scroll">
                    <h4 class="card-title">{{__('teacher')}}</h4>
                    @foreach($teachers as $row)
                    <div class="wrapper d-flex align-items-center py-2 border-bottom">
                        @if(isset($row->user->image))
                        <img class="img-sm rounded-circle" src="{{$row->user->image}}" alt="profile" onerror="onErrorImage(event)">
                        @else
                        <div style="width: 43px; height: 43px;"></div>
                        @endif
                        <div class="wrapper ml-3">
                            <h6 class="ml-1 mb-1">@if(isset($row->user->first_name)){{$row->user->first_name.' '.$row->user->last_name}}@endif</h6>
                            <small class="text-muted mb-0"><i class="mdi mdi-map-marker-outline mr-1"></i>@if(isset($row->qualification)){{$row->qualification}}@endif</small>
                        </div>
                        <div class="badge badge-pill badge-success ml-auto px-1 py-1">
                            <i class="mdi mdi-check"></i>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        @if($boys || $girls)
        <div class="col-md-5 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">{{__('student')}}'s {{__('gender')}}</h4>
                    <canvas id="gender-ratio-chart"></canvas>
                    <div id="gender-ratio-chart-legend" class="rounded-legend legend-vertical legend-bottom-left pt-4"></div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($announcement)
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">{{__('noticeboard')}}</h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th> {{__('no.')}}</th>
                                <th> {{__('title')}}</th>
                                <th> {{__('description')}}</th>
                                @hasrole(['Super Admin'])
                                <th> {{__('School')}}</th>
                                @endrole
                                <th> {{__('date')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($announcement as $key=>$row)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$row->title}}</td>
                                <td class="description">{{$row->description}}</td>
                                @hasrole(['Super Admin'])
                                @if($row->school)
                                <td>{{$row->school->school_name}}</td>
                                @else
                                <td>All Schools</td>
                                @endif
                                @endrole
                                <td>{{$row->created_at_for_web}}</td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
@section('script')
@if($boys || $girls)
<script>
    (function ($) {
        'use strict';
        $(function () {
            Chart.defaults.global.legend.labels.usePointStyle = true;
            if ($("#gender-ratio-chart").length) {
                let ctx = document.getElementById('gender-ratio-chart').getContext("2d")
                let gradientStrokeBlue = ctx.createLinearGradient(0, 0, 0, 181);
                gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
                gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');
                let gradientLegendBlue = 'linear-gradient(to right, rgba(54, 215, 232, 1), rgba(177, 148, 250, 1))';

                let gradientStrokeRed = ctx.createLinearGradient(0, 0, 0, 50);
                gradientStrokeRed.addColorStop(0, 'rgba(255, 191, 150, 1)');
                gradientStrokeRed.addColorStop(1, 'rgba(254, 112, 150, 1)');
                let gradientLegendRed = 'linear-gradient(to right, rgba(255, 191, 150, 1), rgba(254, 112, 150, 1))';
                let trafficChartData = {
                    datasets: [{
                        data: [{{$boys}}, {{$girls}}],
                backgroundColor: [
                    gradientStrokeBlue,
                    gradientStrokeRed
                ],
                    hoverBackgroundColor: [
                    gradientStrokeBlue,
                    gradientStrokeRed
                ],
                    borderColor: [
                    gradientStrokeBlue,
                    gradientStrokeRed
                ],
                    legendColor: [
                    gradientLegendBlue,
                    gradientLegendRed
                ]
            }],

                // These labels appear in the legend and in the tooltips when hovering different arcs
                labels: [
                    "{{__('male')}}",
                    "{{__('female')}}"
                ]
            };
                let trafficChartOptions = {
                    responsive: true,
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    },
                    legend: false,
                    legendCallback: function (chart) {
                        let text = [];
                        text.push('<ul>');
                        for (let i = 0; i < trafficChartData.datasets[0].data.length; i++) {
                            text.push('<li><span class="legend-dots" style="background:' + trafficChartData.datasets[0].legendColor[i] + '"></span>');
                            if (trafficChartData.labels[i]) {
                                text.push(trafficChartData.labels[i]);
                            }
                            text.push('<span class="float-right">' + trafficChartData.datasets[0].data[i] + "%" + '</span>')
                            text.push('</li>');
                        }
                        text.push('</ul>');
                        return text.join('');
                    }
                };
                let trafficChartCanvas = $("#gender-ratio-chart").get(0).getContext("2d");
                let trafficChart = new Chart(trafficChartCanvas, {
                    type: 'doughnut',
                    data: trafficChartData,
                    options: trafficChartOptions
                });
                $("#gender-ratio-chart-legend").html(trafficChart.generateLegend());
            }
            if ($("#inline-datepicker").length) {
                $('#inline-datepicker').datepicker({
                    enableOnReadonly: true,
                    todayHighlight: true,
                });
            }
        });
    })(jQuery);
</script>
@endif
<script>


    const messagings = firebaseInit.messaging();
    messagings.usePublicVapidKey("BMF317nYWC_7EZ2EcldU5OS2IhGO1UE0yXWV7TgBZ1mekcXe5BTrckxBqRhiWIWzglBvWbtAWST1md1C5rTZiz4");

    function sendTokenToServer(fcm_token) {
        const user_id = '{{auth()->user()->id}}';
        axios.post('/save-token', {
            fcm_token, user_id
        })
            .then(res => {
                console.log(res);
            })
    }

    function retreiveToken() {
        messagings.getToken().then((currentToken) => {
            if (currentToken) {
                sendTokenToServer(currentToken);
            } else {

                alert('You should allow notification!');
            }
        }).catch((err) => {
            console.log(err.message);
        });
    }

    retreiveToken();
    messagings.onTokenRefresh(() => {
        console.log('onTokenRefresh');
        retreiveToken();
    });

    messagings.onMessage((res) => {
        console.log('Message received');
    });

</script>
@endsection
