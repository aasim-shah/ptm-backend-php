<?php

namespace App\Imports;

use Throwable;
use App\Models\User;
use App\Models\Parents;
use App\Models\Category;
use App\Models\Students;
use App\Models\SessionYear;
use Illuminate\Support\Arr;
use GuzzleHttp\Psr7\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToCollection, WithHeadingRow
{
    public $class_section_id;
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function  __construct($class_section_id)
    {
        $this->class_section_id = $class_section_id;
    }
    public function collection(Collection $rows)
    {
        $validator = Validator::make($rows->toArray(), [
            '*.first_name' => 'required',
            '*.last_name' => 'required',
            '*.mobile' => 'nullable|regex:/^([0-9\s\-\+\(\)]*)$/',
            '*.gender' => 'required',
            '*.dob' => 'required|date',
            '*.category' => 'required',
            '*.caste' => 'nullable',
            '*.religion' => 'nullable',
            '*.admission_date' => 'required|date',
            '*.blood_group' => 'required',
            '*.height' => 'required',
            '*.wieght' => 'required',
            '*.current_address' => 'required',
            '*.permanent_address' => 'required',
        ]);

        $validator->validate();
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $father_user = new User();
        $mother_user = new User();
        $parentRole = Role::where('name', 'Parent')->first();
        $studentRole = Role::where('name', 'Student')->first();

        foreach ($rows as $row) {
            $validator = Validator::make($row->toArray(), [
                'father_email' => 'required|email:dns',
                'mother_email' => 'required|email:dns'
            ]);
            $validator->validate();
            if ($validator->fails()) {
                $response = array(
                    'error' => true,
                    'message' => $validator->errors()->first()
                );
                return response()->json($response);
            }
            $father_email_check = Parents::select('email')->where('email', $row['father_email'])->count();
            $father_plaintext_password = str_replace('-', '', date('d-m-Y', strtotime($row['father_dob'])));
            if ($father_email_check == 0) {
                $validator = Validator::make($row->toArray(), [
                    'father_first_name' => 'required',
                    'father_last_name' => 'required',
                    'father_mobile' => 'required',
                    'father_dob' => 'required',
                    'father_occupation' => 'required',
                ]);

                $validator->validate();
                if ($validator->fails()) {
                    $response = array(
                        'error' => true,
                        'message' => $validator->errors()->first()
                    );
                    return response()->json($response);
                }
                $father_user = new User();
                $father_user->first_name = $row['father_first_name'];
                $father_user->last_name = $row['father_last_name'];
                $father_user->email = $row['father_email'];
                $father_user->password = Hash::make($father_plaintext_password);
                $father_user->mobile = $row['father_mobile'];
                $father_user->image = 'dummy_logo.jpg';
                $father_user->dob = date('Y-m-d', strtotime($row['father_dob']));
                $father_user->gender = 'Male';
                $father_user->save();
                $father_user->assignRole($parentRole);

                $father_parent = new Parents();
                $father_parent->user_id = $father_user->id;
                $father_parent->first_name = $row['father_first_name'];
                $father_parent->last_name = $row['father_last_name'];
                $father_parent->image = 'dummy_logo.jpg';
                $father_parent->occupation = $row['father_occupation'];
                $father_parent->mobile = $row['father_mobile'];
                $father_parent->email = $row['father_email'];
                $father_parent->dob = date('Y-m-d', strtotime($row['father_dob']));
                $father_parent->gender = 'Male';
                $father_parent->save();
                $father_parent_id = $father_parent->id;
                $father_email = $row['father_email'];
                $father_name = $row['father_first_name'];
            } else {
                $father_parent_id = Parents::where('email', $row['father_email'])->pluck('user_id')->first();
                $father_name = Parents::where('email', $row['father_email'])->pluck('first_name')->first();
                $father_email = $row['father_email'];
            }
            $mother_email_check = Parents::select('email')->where('email', $row['mother_email'])->count();
            $mother_plaintext_password = str_replace('-', '', date('d-m-Y', strtotime($row['mother_dob'])));
            if ($mother_email_check == 0) {
                $validator = Validator::make($row->toArray(), [
                    'mother_first_name' => 'required',
                    'mother_last_name' => 'required',
                    'mother_mobile' => 'required',
                    'mother_dob' => 'required',
                    'mother_occupation' => 'required',
                ]);

                $validator->validate();
                if ($validator->fails()) {
                    $response = array(
                        'error' => true,
                        'message' => $validator->errors()->first()
                    );
                    return response()->json($response);
                }
                $mother_user = new User();
                $mother_user->image = 'dummy_logo.jpg';
                $mother_user->password = Hash::make($mother_plaintext_password);
                $mother_user->first_name = $row['mother_first_name'];
                $mother_user->last_name = $row['mother_last_name'];
                $mother_user->email = $row['mother_email'];
                $mother_user->mobile = $row['mother_mobile'];
                $mother_user->dob = date('Y-m-d', strtotime($row['mother_dob']));
                $mother_user->gender = 'Female';
                $mother_user->save();
                $mother_user->assignRole($parentRole);

                $mother_parent = new Parents();
                $mother_parent->user_id = $mother_user->id;
                $mother_parent->first_name = $row['mother_first_name'];
                $mother_parent->last_name = $row['mother_last_name'];
                $mother_parent->image = 'dummy_logo.jpg';
                $mother_parent->occupation = $row['mother_occupation'];
                $mother_parent->mobile = $row['mother_mobile'];
                $mother_parent->email = $row['mother_email'];
                $mother_parent->dob = date('Y-m-d', strtotime($row['mother_dob']));
                $mother_parent->gender = 'Female';
                $mother_parent->save();
                $mother_parent_id = $mother_parent->id;
                $mother_email = $row['mother_email'];
                $mother_name = $row['mother_first_name'];
            } else {
                $mother_parent_id = Parents::where('email', $row['mother_email'])->pluck('user_id')->first();
                $mother_name = Parents::where('email', $row['mother_email'])->pluck('first_name')->first();
                $mother_email = $row['mother_email'];
            }
            if ($row['guardian'] == "yes") {
                Validator::make($row->toArray(), [
                    'guardian_email' => 'required|email:dns',
                ])->validate();
                $guardian_email_check = Parents::select('email')->where('email', $row['guardian_email'])->count();
                if ($guardian_email_check == 0) {
                    $validator = Validator::make($row->toArray(), [
                        'guardian_first_name' => 'required',
                        'guardian_last_name' => 'required',
                        'guardian_email' => 'required|email:dns|unique:parents,email',
                        'guardian_mobile' => 'required|digits:10|nullable|regex:/^([0-9\s\-\+\(\)]*)$/',
                        'guardian_dob' => 'required',
                        'guardian_occupation' => 'required',
                    ]);
                    $validator->validate();
                    if ($validator->fails()) {
                        $response = array(
                            'error' => true,
                            'message' => $validator->errors()->first()
                        );
                        return response()->json($response);
                    }
                    $guardian_parent = new Parents();
                    $guardian_parent->user_id = 0;
                    $guardian_parent->first_name = $row['guardian_first_name'];
                    $guardian_parent->last_name = $row['guardian_last_name'];
                    $guardian_parent->image = 'dummy_logo.jpg';
                    $guardian_parent->occupation = $row['guardian_occupation'];
                    $guardian_parent->mobile = $row['guardian_mobile'];
                    $guardian_parent->email = $row['guardian_email'];
                    $guardian_parent->dob = date('Y-m-d', strtotime($row['guardian_dob']));
                    $guardian_parent->gender = $row['guardian_gender'];
                    $guardian_parent->save();
                    $guardian_parent_id = $guardian_parent->id;
                    $guardian_email = $row['guardian_email'];
                    $guardian_name = $row['guardian_first_name'];
                } else {
                    $guardian_parent_id = Parents::where('email', $row['guardian_email'])->pluck('user_id')->first();
                    $guardian_name = Parents::where('email', $row['guardian_email'])->pluck('user_id')->first();
                    $guardian_email = $row['guardian_email'];
                }
            } else {
                $guardian_parent_id = 0;
            }

            $data = getSettings('session_year');
            if (empty($data)) {
                $response = array(
                    'error' => true,
                    'message' => trans('please_set_session_year_first')
                );
                return response()->json($response);
            } else {
                $session_year = SessionYear::select('name')->where('id', $data['session_year'])->pluck('name')->first();
                $get_student = Students::withTrashed()->latest('id')->pluck('user_id')->first();
                $admission_no = $session_year . ($get_student + 1);
            }
            $category_id = Category::where('name', $row['category'])->pluck('id')->first();
            if($category_id){
                $category_id = $category_id;
            }else{
                $category = new Category();
                $category->name = $row['category'];
                $category->save();
                $category_id = $category->id;
            }
            $user = new User();
            $child_plaintext_password = str_replace('-', '', date('d-m-Y', strtotime($row['dob'])));
            $user->password = Hash::make($child_plaintext_password);
            $user->first_name = $row['first_name'];
            $user->last_name = $row['last_name'];
            $user->email = $admission_no;
            $user->gender = $row['gender'];
            $user->mobile = $row['mobile'];
            $user->image = 'dummy_logo.jpg';
            $user->dob = date('Y-m-d', strtotime($row['dob']));
            $user->current_address = $row['current_address'];
            $user->permanent_address = $row['permanent_address'];
            $user->save();
            $user->assignRole($studentRole);

            $student = new Students();
            $student->user_id = $user->id;
            $student->class_section_id = $this->class_section_id;
            $student->category_id = $category_id;
            $student->admission_no = $admission_no;
            if(isset($row['caste']) && !empty($row['caste'])){
                $student->caste = $row['caste'];
            }
            if(isset($row['religion']) && !empty($row['religion'])){
                $student->religion = $row['religion'];
            }
            $student->admission_date = date('Y-m-d', strtotime($row['admission_date']));
            $student->blood_group = $row['blood_group'];
            $student->height = $row['height'];
            $student->weight = $row['wieght'];
            $student->father_id = $father_parent_id;
            $student->mother_id = $mother_parent_id;
            $student->guardian_id = $guardian_parent_id;
            $student->save();

            //Send User Credentials via Email
            $school_name = getSettings('school_name');
            $father_data = [
                'subject' => 'Welcome to ' . $school_name['school_name'],
                'email' => $father_email,
                'name' => ' ' . $father_name,
                'username' => ' ' . $father_email,
                'password' => ' ' . $father_plaintext_password,
                'child_name' => ' ' . $row['first_name'],
                'child_grnumber' => ' ' . $admission_no,
                'child_password' => ' ' . $child_plaintext_password,
            ];
            Mail::send('students.email', $father_data, function ($message) use ($father_data) {
                $message->to($father_data['email'])->subject($father_data['subject']);
            });

            $mother_data = [
                'subject' => 'Welcome to ' . $school_name['school_name'],
                'email' => $mother_email,
                'name' => ' ' . $mother_name,
                'username' => ' ' . $mother_email,
                'password' => ' ' . $mother_plaintext_password,
                'child_name' => ' ' . $row['first_name'],
                'child_grnumber' => ' ' . $admission_no,
                'child_password' => ' ' . $child_plaintext_password,
            ];
            Mail::send('students.email', $mother_data, function ($message) use ($mother_data) {
                $message->to($mother_data['email'])->subject($mother_data['subject']);
            });
        }
    }
}
