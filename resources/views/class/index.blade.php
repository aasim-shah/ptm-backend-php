<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
@extends('layouts.master')

@section('title')
    {{ __('class') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage') . ' ' . __('class') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('create') . ' ' . __('class') }}
                        </h4>
                        <form class="pt-3 class-create-form" id="create-form" action="{{ route('class.store') }}"
                            method="POST" novalidate="novalidate">
                            <div class="form-group">
                                <label>{{ __('name') }} <span class="text-danger">*</span></label>
                                <input name="name" type="text" placeholder="{{ __('name') }}"
                                    class="form-control" />
                            </div>
                            <div class="form-group">
                                <label>{{ __('grade') }} <span class="text-danger">*</span></label>
                                <input name="grade" type="text" placeholder="{{ __('grade') }}"
                                    class="form-control" />
                            </div>

                            <div class="form-group">
                                <label>{{ __('section') }} <span class="text-danger">*</span></label>
                                @foreach ($sections as $section)
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" id="{{ $section->id }}" class="form-check-input" onclick="checkClick( {{ $section->id }})" name="section_id[]"
                                                value="{{ $section->id }}">{{ $section->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="row">
                                <input class="btn btn-theme" id="create-btn" type="submit" value={{ __('submit') }}>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list') . ' ' . __('class') }}
                        </h4>
                        <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table"
                            data-url="{{ url('class-list') }}" data-click-to-select="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200, All]" data-search="true"
                            data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="id" data-toolbar="#toolbar" data-sort-order="desc"
                            data-maintain-selected="true" data-export-types='["txt","excel"]'
                            data-export-options='{ "fileName": "class-list-<?= date('d-m-y') ?>" ,"ignoreColumn":
                            ["operate"]}'
                            data-show-export="true"
                            data-query-params="classQueryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}</th>
                                    <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('name') }}</th>
                                    <th scope="col" data-field="grade" data-sortable="true">{{ __('grade') }}
                                    </th>
                                    <th scope="col" data-field="section_names" data-sortable="true">
                                        {{ __('section') }}
                                    </th>
                                    <th scope="col" data-field="created_at" data-sortable="true" data-visible="false">
                                        {{ __('created_at') }}</th>
                                    <th scope="col" data-field="updated_at" data-sortable="true" data-visible="false">
                                        {{ __('updated_at') }}</th>
                                    <th scope="col" data-field="operate" data-sortable="false"
                                        data-events="classEvents">{{ __('action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">{{ __('edit') . ' ' . __('class') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form class="pt-3 class-edit-form" id="edit-form" action="{{ url('class') }}"
                            novalidate="novalidate">
                            <div class="modal-body">
                                <input type="hidden" name="edit_id" id="edit_id" value="" />
                                <div class="form-group">
                                    <label>{{ __('name') }} <span class="text-danger">*</span></label>
                                    <input name="name" id="edit_name" type="text"
                                        placeholder="{{ __('name') }}" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>{{ __('grade') }} <span class="text-danger">*</span></label>
                                    <input name="grade" id="grade" type="text"
                                           placeholder="{{ __('grade') }}" class="form-control" />
                                </div>
<!--                                <div class="form-group">-->
<!--                                    <label>{{ __('section') }} <span class="text-danger">*</span></label>-->
<!--                                    @foreach ($sections as $section)-->
<!--                                        <div class="form-check">-->
<!--                                            <label class="form-check-label">-->
<!--                                                <input type="checkbox" class="form-check-input edit" name="section_id[]"-->
<!--                                                    id="edit_section_id"-->
<!--                                                    value="{{ $section->id }}">{{ $section->name }}-->
<!--                                            </label>-->
<!--                                        </div>-->
<!--                                    @endforeach-->
<!--                                </div>-->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-dismiss="modal">{{ __('close') }}</button>
                                <input class="btn btn-theme" type="submit" value={{ __('edit') }} />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

<script>

    function checkClick(element_id) {
        $('.form-check-input').prop('checked', false);
        $('#'+element_id).prop('checked', true);
    }

</script>