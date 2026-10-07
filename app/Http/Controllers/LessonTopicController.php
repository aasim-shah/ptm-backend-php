<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\LessonTopic;
use App\Models\ClassSection;
use App\Models\Students;
use App\Rules\uniqueTopicInLesson;
use App\Rules\YouTubeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LessonTopicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Auth::user()->can('topic-list')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $class_section = ClassSection::SubjectTeacher()->with('class', 'section')->get();
        $subjects = Subject::SubjectTeacher()->orderBy('id', 'ASC')->get();
        $lessons = Lesson::get();
        return response(view('lessons.topic', compact('class_section', 'subjects', 'lessons')));
    }

    /**
     *
     * /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('topic-create')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $validator = Validator::make(
            $request->all(),
            [
                'class_section_id' => 'required|numeric',
                'subject_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
                'name' => ['required', new uniqueTopicInLesson($request->lesson_id)],
                'description' => 'required',

                'edit_file' => 'nullable|array',
                'edit_file.*.type' => 'nullable|in:file_upload,youtube_link,video_upload,other_link',
                'edit_file.*.name' => 'required_with:edit_file.*.type',
                'edit_file.*.thumbnail' => 'required_if:edit_file.*.type,youtube_link,video_upload,other_link',
                //Regex for Youtube Link
                'edit_file.*.link' => ['nullable','required_if:edit_file.*.type,youtube_link', new YouTubeUrl],

                'file' => 'nullable|array',
                'file.*.type' => 'nullable|in:file_upload,youtube_link,video_upload,other_link',
                'file.*.name' => 'required_with:file.*.type',
                'file.*.thumbnail' => 'required_if:file.*.type,youtube_link,video_upload,other_link',
                'file.*.file' => 'required_if:file.*.type,file_upload,video_upload',
                //Regex for Youtube Link
                'file.*.link' => ['nullable','required_if:file.*.type,youtube_link', new YouTubeUrl],
                //Regex for Other Link
                // 'file.*.link'=>'required_if:file.*.type,other_link|url'
            ],
        );

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
            );
            return response()->json($response);
        }
        try {
            $topic = new LessonTopic();
            $topic->name = $request->name;
            $topic->description = $request->description;
            $topic->lesson_id = $request->lesson_id;
            $topic->save();

            foreach ($request->file as $data) {
                if ($data['type']) {
                    $file = new File();
                    $file->file_name = $data['name'];
                    $file->modal()->associate($topic);

                    if ($data['type'] == "file_upload") {
                        $file->type = 1;
                        $file->file_url = $data['file']->store('lessons', 'public');
                    } elseif ($data['type'] == "youtube_link") {
                        $file->type = 2;

                        $image = $data['thumbnail'];
                        // made file name with combination of current time
                        $file_name = time() . '-' . $image->getClientOriginalName();
                        //made file path to store in database
                        $file_path = 'lessons/' . $file_name;
                        //resized image
                        resizeImage($image);
                        //stored image to storage/public/lessons folder
                        $destinationPath = storage_path('app/public/lessons');
                        $image->move($destinationPath, $file_name);

                        $file->file_thumbnail = $file_path;
                        $file->file_url = $data['link'];
                    } elseif ($data['type'] == "video_upload") {
                        $file->type = 3;

                        $image = $data['thumbnail'];
                        // made file name with combination of current time
                        $file_name = time() . '-' . $image->getClientOriginalName();
                        //made file path to store in database
                        $file_path = 'lessons/' . $file_name;
                        //resized image
                        resizeImage($image);
                        //stored image to storage/public/lessons folder
                        $destinationPath = storage_path('app/public/lessons');
                        $image->move($destinationPath, $file_name);

                        $file->file_thumbnail = $file_path;
                        $file->file_url = $data['file']->store('lessons', 'public');
                    } elseif ($data['type'] == "other_link") {
                        $file->type = 4;

                        $image = $data['thumbnail'];
                        // made file name with combination of current time
                        $file_name = time() . '-' . $image->getClientOriginalName();
                        //made file path to store in database
                        $file_path = 'lessons/' . $file_name;
                        //resized image
                        resizeImage($image);
                        //stored image to storage/public/lessons folder
                        $destinationPath = storage_path('app/public/lessons');
                        $image->move($destinationPath, $file_name);

                        $file->file_thumbnail = $file_path;

                        $file->file_url = $data['link'];
                    }

                    $file->save();
                }
            }

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'exception' => $e
            );
        }
        return response()->json($response);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\LessonTopic $lessonTopic
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        if (!Auth::user()->can('topic-list')) {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }
        $offset = 0;
        $limit = 10;
        $sort = 'id';
        $order = 'DESC';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'] ?? 0;
        if (isset($_GET['limit']))
            $limit = $_GET['limit'] ?? 10;
        if (isset($_GET['sort']))
            $sort = $_GET['sort'] ?? 'id';
        if (isset($_GET['order']))
            $order = $_GET['order'] ?? 'DESC';

        $sql = LessonTopic::lessontopicteachers()->with('lesson.class_section', 'lesson.subject', 'file');
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'] ?? '';
            $sql->where('id', 'LIKE', "%$search%")
                ->orwhere('name', 'LIKE', "%$search%")
                ->orwhere('description', 'LIKE', "%$search%")
                ->orWhereHas('lesson.class_section.section', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('lesson.class_section.class', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('lesson.subject', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                })->orWhereHas('lesson', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%");
                });
        }
        if ($_GET['subject_id']) {

            $sql = $sql->whereHas('lesson', function ($q) {
                $q->where('subject_id', $_GET['subject_id']);
            });
        }
        if ($_GET['class_id']) {

            $sql = $sql->whereHas('lesson', function ($q) {
                $q->where('class_section_id', $_GET['class_id']);
            });
        }
        if ($_GET['lesson_id']) {
            $sql = $sql->where('lesson_id', $_GET['lesson_id']);
        }
        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $no = 1;
        foreach ($res as $row) {

            $row = (object)$row;
            $operate = '<a href=' . route('lesson-topic.edit', $row->id) . ' class="btn btn-xs btn-gradient-primary btn-rounded btn-icon edit-data" data-id=' . $row->id . ' title="Edit" data-toggle="modal" data-target="#editModal"><i class="fa fa-edit"></i></a>&nbsp;&nbsp;';
            $operate .= '<a href=' . route('lesson-topic.destroy', $row->id) . ' class="btn btn-xs btn-gradient-danger btn-rounded btn-icon delete-form" data-id=' . $row->id . '><i class="fa fa-trash"></i></a>';

            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['name'] = $row->name;
            $tempRow['description'] = $row->description;
            $tempRow['lesson_id'] = $row->lesson_id;
            $tempRow['lesson_name'] = $row->lesson->name;
            $tempRow['class_section_id'] = $row->lesson->class_section->id;
            $tempRow['class_section_name'] = $row->lesson->class_section->class->name . ' ' . $row->lesson->class_section->section->name;
            $tempRow['subject_id'] = $row->lesson->subject->id;
            $tempRow['subject_name'] = $row->lesson->subject->name.' - '.$row->lesson->subject->type;
            $tempRow['file'] = $row->file;
            $tempRow['created_at'] = $row->created_at;
            $tempRow['updated_at'] = $row->updated_at;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\LessonTopic $lessonTopic
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, LessonTopic $lessonTopic)
    {
        if (!Auth::user()->can('topic-edit')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $validator = Validator::make(
            $request->all(),
            [
                'edit_id' => 'required|numeric',
                'class_section_id' => 'required|numeric',
                'subject_id' => 'required|numeric',
                'name' => ['required', new uniqueTopicInLesson($request->lesson_id,$request->edit_id)],
                'description' => 'required',

                'edit_file' => 'nullable|array',
                'edit_file.*.type' => 'nullable|in:file_upload,youtube_link,video_upload,other_link',
                'edit_file.*.name' => 'nullable|required_with:edit_file.*.type',
                //Regex for Youtube Link
                'edit_file.*.link' => ['nullable','required_if:edit_file.*.type,youtube_link', new YouTubeUrl],

                'file' => 'nullable|array',
                'file.*.type' => 'nullable|in:file_upload,youtube_link,video_upload,other_link',
                'file.*.name' => 'nullable|required_with:file.*.type',
                'file.*.thumbnail' => 'nullable|required_if:file.*.type,youtube_link,video_upload,other_link',
                'file.*.file' => 'nullable|required_if:file.*.type,file_upload,video_upload',
                //Regex for Youtube Link
                'file.*.link' => ['nullable','required_if:file.*.type,youtube_link', new YouTubeUrl],
            ],
            [
                'name.unique' => trans('topic_alredy_exists')
            ]
        );
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
            );
            return response()->json($response);
        }
        try {
            $topic = LessonTopic::find($request->edit_id);
            $topic->name = $request->name;
            $topic->description = $request->description;
            $topic->save();

            // Update the Old Files
            foreach ($request->edit_file as $key => $file) {
                if ($file['type']) {
                    $topic_file = File::find($file['id']);
                    $topic_file->file_name = $file['name'];

                    if ($file['type'] == "file_upload") {
                        $topic_file->type = 1;
                        if (!empty($file['file'])) {
                            if (Storage::disk('public')->exists($topic_file->getRawOriginal('file_url'))) {
                                Storage::disk('public')->delete($topic_file->getRawOriginal('file_url'));
                            }
                            $topic_file->file_url = $file['file']->store('lessons', 'public');
                        }
                    } elseif ($file['type'] == "youtube_link") {
                        $topic_file->type = 2;
                        if (!empty($file['thumbnail'])) {
                            if (Storage::disk('public')->exists($topic_file->getRawOriginal('file_thumbnail'))) {
                                Storage::disk('public')->delete($topic_file->getRawOriginal('file_thumbnail'));
                            }

                            $image = $file['thumbnail'];
                            // made file name with combination of current time
                            $file_name = time() . '-' . $image->getClientOriginalName();
                            //made file path to store in database
                            $file_path = 'lessons/' . $file_name;
                            //resized image
                            resizeImage($image);
                            //stored image to storage/public/lessons folder
                            $destinationPath = storage_path('app/public/lessons');
                            $image->move($destinationPath, $file_name);

                            $topic_file->file_thumbnail = $file_path;
                        }

                        $topic_file->file_url = $file['link'];
                    } elseif ($file['type'] == "video_upload") {
                        $topic_file->type = 3;
                        if (!empty($file['file'])) {
                            if (Storage::disk('public')->exists($topic_file->getRawOriginal('file_url'))) {
                                Storage::disk('public')->delete($topic_file->getRawOriginal('file_url'));
                            }
                            $topic_file->file_url = $file['file']->store('lessons', 'public');
                        }

                        if (!empty($file['thumbnail'])) {
                            if (Storage::disk('public')->exists($topic_file->getRawOriginal('file_thumbnail'))) {
                                Storage::disk('public')->delete($topic_file->getRawOriginal('file_thumbnail'));
                            }
                            $image = $file['thumbnail'];
                            // made file name with combination of current time
                            $file_name = time() . '-' . $image->getClientOriginalName();
                            //made file path to store in database
                            $file_path = 'lessons/' . $file_name;
                            //resized image
                            resizeImage($image);
                            //stored image to storage/public/lessons folder
                            $destinationPath = storage_path('app/public/lessons');
                            $image->move($destinationPath, $file_name);

                            $topic_file->file_thumbnail = $file_path;
                        }
                    } elseif ($file['type'] == "other_link") {
                        $topic_file->type = 4;
                        if (!empty($file['thumbnail'])) {
                            if (Storage::disk('public')->exists($topic_file->getRawOriginal('file_thumbnail'))) {
                                Storage::disk('public')->delete($topic_file->getRawOriginal('file_thumbnail'));
                            }
                            $image = $file['thumbnail'];
                            // made file name with combination of current time
                            $file_name = time() . '-' . $image->getClientOriginalName();
                            //made file path to store in database
                            $file_path = 'lessons/' . $file_name;
                            //resized image
                            resizeImage($image);
                            //stored image to storage/public/lessons folder
                            $destinationPath = storage_path('app/public/lessons');
                            $image->move($destinationPath, $file_name);

                            $topic_file->file_thumbnail = $file_path;
                        }
                        $topic_file->file_url = $file['link'];
                    }

                    $topic_file->save();
                }
            }

            //Add the new Files
            if ($request->file) {
                foreach ($request->file as $key => $file) {
                    $topic_file = new File();
                    $topic_file->file_name = $file['name'];
                    $topic_file->modal()->associate($topic);

                    if ($file['type'] == "file_upload") {
                        $topic_file->type = 1;
                        $topic_file->file_url = $file['file']->store('lessons', 'public');
                    } elseif ($file['type'] == "youtube_link") {
                        $topic_file->type = 2;
                        $image = $file['thumbnail'];
                        // made file name with combination of current time
                        $file_name = time() . '-' . $image->getClientOriginalName();
                        //made file path to store in database
                        $file_path = 'lessons/' . $file_name;
                        //resized image
                        resizeImage($image);
                        //stored image to storage/public/lessons folder
                        $destinationPath = storage_path('app/public/lessons');
                        $image->move($destinationPath, $file_name);

                        $topic_file->file_thumbnail = $file_path;
                        $topic_file->file_url = $file['link'];
                    } elseif ($file['type'] == "video_upload") {
                        $topic_file->type = 3;
                        $topic_file->file_url = $file['file']->store('lessons', 'public');

                        $image = $file['thumbnail'];
                        // made file name with combination of current time
                        $file_name = time() . '-' . $image->getClientOriginalName();
                        //made file path to store in database
                        $file_path = 'lessons/' . $file_name;
                        //resized image
                        resizeImage($image);
                        //stored image to storage/public/lessons folder
                        $destinationPath = storage_path('app/public/lessons');
                        $image->move($destinationPath, $file_name);

                        $topic_file->file_thumbnail = $file_path;
                    } elseif ($file['type'] == "other_link") {
                        $topic_file->type = 4;

                        $image = $file['thumbnail'];
                        // made file name with combination of current time
                        $file_name = time() . '-' . $image->getClientOriginalName();
                        //made file path to store in database
                        $file_path = 'lessons/' . $file_name;
                        //resized image
                        resizeImage($image);
                        //stored image to storage/public/lessons folder
                        $destinationPath = storage_path('app/public/lessons');
                        $image->move($destinationPath, $file_name);

                        $topic_file->file_thumbnail = $file_path;
                        $topic_file->file_url = $file['link'];
                    }
                    $topic_file->save();
                }
            }


            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully')
            );
        } catch (Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'exception' => $e
            );
        }
        return response()->json($response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\LessonTopic $lessonTopic
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Auth::user()->can('topic-delete')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        try {
            $topic = LessonTopic::find($id);
            if ($topic->file) {
                foreach ($topic->file as $file) {
                    if (Storage::disk('public')->exists($file->file_url)) {
                        Storage::disk('public')->delete($file->file_url);
                    }
                }
            }
            $topic->file()->delete();
            $topic->delete();
            $response = array(
                'error' => false,
                'message' => trans('data_delete_successfully')
            );
        } catch (\Throwable $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred')
            );
        }
        return response()->json($response);
    }
}
