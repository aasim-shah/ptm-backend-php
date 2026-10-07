@extends('layouts.master')

@section('title')
{{ __('user_details') }}
@endsection

@section('content')
<script src="{{ mix('js/app.js') }}" defer></script>

<style>
    #video-container{
        margin-left: -25% !important;
        margin-top: -60% !important;
        z-index: 1 !important;
    }
    .action-btns{
        left: 39% !important;
        margin-left: 0;
    }
</style>
<div class="content-wrapper" style="font-size: 14px">
    <div class="page-header">
        <h3 class="page-title">
            {{__('user_details') }}
        </h3>
    </div>


    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <table width="100%">
                        <tr>
                            <td>
                                @if(isset($user->image))
                                <img width="10%" src="{{$user->image}}" alt="">
                                @endif
                            </td>
                        </tr>
                    </table>
                    <br>
                    <table width="100%">
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td><b>First Name</b></td>
                            <td>{{ $user->first_name }}</td>
                            <td width="100px">&nbsp;</td>
                            <td><b>Last Name</b></td>
                            <td>{{ $user->last_name }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td><b>Email</b></td>
                            <td>{{ $user->email }}</td>
                            <td width="100px">&nbsp;</td>
                            <td><b>Status</b></td>
                            <td>
                                @if($user->status)
                                <span class="status-1">Active</span>
                                @endif
                                @if(!$user->status)
                                <span class="status-0">Inactive</span>
                                @endif
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td><b>Language</b></td>
                            <td>{{ $user->language }}</td>
                            <td width="100px">&nbsp;</td>
                            <td><b>Joined</b></td>
                            <td>{{ $user->created_at }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            @if($user->parent)
                            <td><b>Occupation</b></td>
                            <td>{{ $user->parent->occupation }}</td>
                            @endif
<!--                            @if($user->teacher)-->
<!--                            <td><b>Qualification</b></td>-->
<!--                            <td>{{ $user->teacher->qualification }}</td>-->
<!--                            @endif-->

                        </tr>

                    </table>
                    @if(auth()->user()->type === 'Principal')
                    <table width="100%">
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td width="20%">
                                &nbsp;
                            </td>
                            <td>
                                <div class="container">
                                    <div class="row">
                                        <div class="col">
                                        </div>
                                        <div class="btn-group float-right" role="group">
                                            @if($user->type === 'teacher')

                                            <a href="{{ route('t_chat', $user->id) }}">

                                                <button
                                                        href="#"
                                                        type="button"
                                                        class="btn btn-info ml-5" style="padding-top: 18px;padding-bottom: 18px">
                                                    Chat &nbsp;
                                                    <i class="fa fa-mail-reply"></i> &nbsp;

                                                </button>
                                            </a>
                                            @else
                                            <a href="{{ route('p_chat', $user->id) }}">

                                                <button
                                                        href="#"
                                                        type="button"
                                                        class="btn btn-info ml-5" style="padding-top: 18px;padding-bottom: 18px">
                                                    Chat &nbsp;
                                                    <i class="fa fa-mail-reply"></i> &nbsp;

                                                </button>
                                            </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection


@section('js')

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


    var userRole = '{{ $user->type }}';
    if (userRole === "teacher"){
        var $aTag = $($('.nav li a')[2]);
        $aTag.parents('.nav-item').last().addClass('active');
    }else {
        var $aTag = $($('.nav li a')[1]);
        $aTag.parents('.nav-item').last().addClass('active');
    }

</script>

@endsection
