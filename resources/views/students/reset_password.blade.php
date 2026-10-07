@extends('layouts.master')

@section('title')
    {{ __('reset_password') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('reset_password') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card">
                    <div class="card-body">
                        <div class="row">

                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                    data-url="{{ url('reset-password-list') }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                    data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                    data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                    data-maintain-selected="true" data-export-types='["txt","excel"]'
                                    data-export-options='{ "fileName": "reset-password-list-<?= date('d-m-y') ?>
                                    ","ignoreColumn": ["operate"]}'
                                    data-query-params="queryParams">
                                    <thead>
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                                {{ __('id') }}</th>
                                            <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                            <th scope="col" data-field="name" data-sortable="false">{{ __('name') }}
                                            </th>
                                            <th scope="col" data-field="email" data-sortable="false">
                                                {{ __('gr_number') }}
                                            </th>
                                            <th data-events="actionEvents" scope="col" data-field="operate"
                                                data-sortable="false">{{ __('action') }}
                                            </th>
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
        window.actionEvents = {
            'click .reset_password': function(e, value, row, index) {
                Swal.fire({
                    title: "{{ __('change_password_title') }}",
                    text: "{{ __('confirm_change_message') }}",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: "{{ __('yes_change_it') }}"
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ url('student-change-password') }}",
                            type: "POST",
                            data: {
                                id: row.id,
                                dob: row.dob
                            },
                            success: function(response) {
                                console.log(response);
                                if (response.error == true) {
                                    showErrorToast(response.message);
                                } else {
                                    showSuccessToast(response.message);
                                    $('#table_list').bootstrapTable('refresh');
                                }
                            }
                        })
                    }
                })

            }
        };
    </script>
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
