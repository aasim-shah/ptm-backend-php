@extends('layouts.master')

@section('title')
{{ __('class') . ' ' . __('subject') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('class') . ' ' . __('subject') }}
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
<!--                    <div id="toolbar">-->
<!--                        <select name="filter_medium_id" id="filter_medium_id" class="form-control">-->
<!--                            <option value="">{{ __('all') }}</option>-->
<!--                            @foreach ($mediums as $medium)-->
<!--                            <option value="{{ $medium->id }}">-->
<!--                                {{ $medium->name }}-->
<!--                            </option>-->
<!--                            @endforeach-->
<!--                        </select>-->
<!--                    </div>-->
                    <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table" data-url="{{ route('class.subject.list') }}" data-click-to-select="true" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200, All]" data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true" data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-export-types='["txt","excel"]' data-query-params="AssignclassQueryParams" data-export-options='{ "fileName": "class-list-<?= date(' d-m-y') ?>" ,"ignoreColumn":
                        ["operate"]}'
                        data-show-export="true">
                        <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                    {{ __('id') }}</th>
                                <th scope="col" data-field="no" data-sortable="false">{{ __('no.') }}</th>
                                <th scope="col" data-field="name" data-sortable="true">{{ __('name') }}</th>
                                <th scope="col" data-field="medium_name" data-sortable="true">{{ __('medium') }}
                                </th>
                                <th scope="col" data-field="section_names" data-sortable="true">
                                    {{ __('section') }}</th>
                                <th scope="col" data-field="core_subject" data-sortable="true" data-formatter="coreSubjectFormatter">{{ __('core_subject') }}</th>
                                <th scope="col" data-field="elective_subject" data-sortable="true" data-formatter="electiveSubjectFormatter">{{ __('elective_subject') }}</th>
                                <th scope="col" data-field="created_at" data-sortable="true" data-visible="false">
                                    {{ __('created_at') }}</th>
                                <th scope="col" data-field="updated_at" data-sortable="true" data-visible="false">
                                    {{ __('updated_at') }}</th>
                                <th scope="col" data-field="operate" data-sortable="false" data-events="classSubjectEvents"> {{ __('action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">
                            {{ __('edit') . ' ' . __('class') . ' ' . __('subject') }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form class="pt-3 class-edit-form" id="edit-form" action="{{ url('class/subject') }}" novalidate="novalidate">
                        <input type="hidden" name="edit_id" id="edit_id" value="" />
                        <div class="modal-body">
                            <div class="form-group">
                                <label>{{ __('class') }} <span class="text-danger">*</span></label>
                                <select name="class_id" id="edit_class_id" class="form-control" readonly>
                                    <option value="">{{ __('select_class') }}</option>
                                    @foreach ($classes as $class)
                                    <option value="{{ $class->id}}" data-medium="{{$class->medium_id}}">
                                        {{ $class->name  }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <h4 title="Core Subjects are the Compulsory Subject." class="mb-3">
                                {{ __('core_subject') }}
                                <span class="fa fa-info-circle pl-2"></span>
                            </h4>
                            {{-- Template for old core subject --}}
                            <div class="row edit-core-subject-div" style="display: none;">
                                <div class="col-11">
                                    <div class="form-group">
                                        <input type="hidden" name="edit_core_subject[0][class_subject_id]" class="edit-class-subject-id form-control" disabled>
                                        <select name="edit_core_subject[0][subject_id]" class="edit-core-subject-id form-control" required="required" disabled>
                                            <option value="">{{ __('select_subject') }}</option>
                                            @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" data-medium-id="{{ $subject->medium_id }}">
                                                {{ $subject->name }} - {{$subject->type}}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-1 pl-0">
                                    <button type="button" class="btn btn-icon btn-inverse-danger remove-core-subject" title="Remove Core Subject">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Template for New Core Subject --}}
                            <div class="row core-subject-div" style="display: none;">
                                <div class="col-11">
                                    <div class="form-group">
                                        <select name="core_subject_id[0]" class="core-subject-id form-control" required="required" disabled>
                                            <option value="">{{ __('select_subject') }}</option>
                                            @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" data-medium-id="{{ $subject->medium_id }}">
                                                {{ $subject->name }} - {{$subject->type}}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-1 pl-0">
                                    <button type="button" class="btn btn-inverse-success btn-icon add-core-subject" title="Add new Core Subject">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Dynamic New Core Subject will be added in this DIV --}}
                            <div class="mt-3 edit-extra-core-subjects"></div>
                            <div>
                                <div class="form-group pl-0 mt-4">
                                    <button type="button" class="col-md-3 btn btn-inverse-success add-new-core-subject">
                                        {{ __('core_subject') }} <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <hr>

                            <h4 class="mb-4" title="Elective Subjects are the subjects where student have the choice to select the subject from the given subjects.">
                                {{ __('elective_subject') }} <span class="fa fa-info-circle pl-2"></span>
                            </h4>
                            {{-- Template for Old Elective Subjects --}}
                            <div id="edit-elective-subject-group-div" class="edit-elective-subject-group-div" style="display: none;">
                                <input type="hidden" name="edit_elective_subjects[0][subject_group_id]" class="edit-elective-subject-group-id form-control" disabled="true" />
                                <div class="row col d-flex align-items-center">
                                    <h5 class="mb-0 group-no">{{ __('group') }}</h5>
                                    <i class="fa fa-2x fa-times-circle text-left pl-1 pr-0  text-danger remove-elective-subject-group"></i>
                                </div>

                                <div class="form-group row">
                                    <div class="col-3 align-items-end elective-subject-div">
                                        <input type="hidden" name="edit_elective_subjects[0][class_subject_id][0]" class="edit-elective-subject-class-id form-control" disabled="true" />
                                        <select name="edit_elective_subjects[0][subject_id][0]" class="form-control edit-elective-subject-name" disabled="true" required="required">
                                            <option value="">{{ __('select_subject') }}</option>
                                            @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" data-medium-id="{{ $subject->medium_id }}">
                                                {{ $subject->name }} - {{$subject->type}}
                                            </option>
                                            @endforeach
                                        </select>
                                        <i class='fa fa-times-circle text-danger col text-right pl-1 pr-0 remove-elective-subject' id="remove-elective-subject" style="visibility: hidden;"></i>
                                    </div>
                                    <span class='mt-3 or'>{{ __('or') }}</span>
                                    <div class="col-3 align-items-end elective-subject-div">
                                        <input type="hidden" name="edit_elective_subjects[0][class_subject_id][1]" class="edit-elective-subject-class-id form-control" disabled="true" />
                                        <select name="edit_elective_subjects[0][subject_id][1]" class="form-control edit-elective-subject-name" disabled="true" required="required">
                                            <option value="">{{ __('select_subject') }}</option>
                                            @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" data-medium-id="{{ $subject->medium_id }}">
                                                {{ $subject->name }} - {{$subject->type}}
                                            </option>
                                            @endforeach
                                        </select>
                                        <i class='fa fa-times-circle text-danger col text-right pl-1 pr-0 remove-elective-subject' id="remove-elective-subject" style="visibility: hidden;"></i>
                                    </div>
                                    <button type="button" class="btn btn-inverse-success btn-icon add-new-elective-subject ml-3" title="Add New Elective Subject" value="1">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                                <div class="form-group row">
                                    <div class="col-3">
                                        <label>{{ __('total_selectable_subjects') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input name="edit_elective_subjects[0][total_selectable_subjects]" type="number" placeholder="{{ __('total_selectable_subjects') }}" class="form-control edit-total-selectable-subject" min="1" max="1" disabled="disabled" required />
                                    </div>
                                </div>
                                <hr>
                            </div>

                            {{-- Template for New Elective Subjects --}}
                            <div id="elective-subject-group-div" class="elective-subject-group-div" style="display: none;">
                                <div class="row col d-flex align-items-center">
                                    <h5 class="mb-0 group-no">{{ __('group') }}</h5>
                                    <i class="fa fa-2x text-left pl-1 pr-0 fa-times-circle text-danger remove-elective-subject-group"></i>
                                </div>

                                <div class="form-group row">
                                    <div class="col-3 align-items-end elective-subject-div">
                                        <select name="elective_subjects[0][subject_id][0]" class="form-control elective-subject-name" disabled="true" required="required">
                                            <option value="">{{ __('select_subject') }}</option>
                                            @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" data-medium-id="{{ $subject->medium_id }}">
                                                {{ $subject->name }} - {{$subject->type}}
                                            </option>
                                            @endforeach
                                        </select>
                                        <i class='fa fa-times-circle text-danger col text-right pl-1 pr-0 remove-elective-subject' id="remove-elective-subject" style="visibility: hidden;"></i>
                                    </div>
                                    <span class='mt-3 or'>{{ __('or') }}</span>
                                    <div class="col-3 align-items-end elective-subject-div">
                                        <select name="elective_subjects[0][subject_id][1]" class="form-control elective-subject-name" disabled="true" required="required">
                                            <option value="">{{ __('select_subject') }}</option>
                                            @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" data-medium-id="{{ $subject->medium_id }}">
                                                {{ $subject->name }} - {{$subject->type}}
                                            </option>
                                            @endforeach
                                        </select>
                                        <i class='fa fa-times-circle text-danger text-right pl-1 pr-0 remove-elective-subject' id="remove-elective-subject" style="visibility: hidden;"></i>
                                    </div>
                                    <button type="button" class="btn btn-inverse-success btn-icon add-new-elective-subject ml-3" title="Add New Elective Subject" value="1">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                                <div class="form-group row">
                                    <div class="col-3">
                                        <label>{{ __('total_selectable_subjects') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input name="elective_subjects[0][total_selectable_subjects]" type="number" placeholder="{{ __('total_selectable_subjects') }}" class="form-control total-selectable-subject" min="1" max="1" disabled="disabled" required />
                                    </div>
                                </div>
                                <hr>
                            </div>
                            {{-- Dynamic New Elective Subject Group will be added in this DIV --}}
                            <div id="edit-extra-elective-subject-group"></div>
                            <div>
                                <div class="form-group pl-0 mt-4">
                                    <button type="button" class="col-md-3 btn btn-inverse-success add-elective-subject-group">
                                        {{ __('elective_subject') }} <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('close') }}</button>
                            <input class="btn btn-theme" type="submit" value={{ __('edit') }} />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
