@extends('layouts.master')

@section('title')
{{ __('holiday') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('list').' '.__('holiday') }}
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card search-container">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <table aria-describedby="mydesc" class='table' id='table_list'
                            data-toggle="table" data-url="{{ url('holiday-list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]"  data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-maintain-selected="true" data-export-types='["txt","excel"]'
                            data-export-options='{ "fileName": "holiday-list-<?= date('d-m-y') ?>","ignoreColumn": ["operate"]}'
                            data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="no" data-sortable="false">{{__('no.')}}</th>
                                    <th scope="col" data-field="date" data-sortable="false">{{__('date')}}</th>
                                    <th scope="col" data-field="title" data-sortable="false">{{__('title')}}</th>
                                    <th scope="col" data-field="description" data-sortable="false">{{__('description')}}</th>
                                 </tr>
                            </thead>
                        </table>
                    </div>
                </div>
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
        };
    }
</script>
@endsection
