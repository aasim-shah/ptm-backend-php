<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TimetableCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request) {
        $response = array();
        foreach ($this->collection as $key => $row) {
            $response[$key] = array(
                "start_time" => $row['start_time'],
                "end_time" => $row['end_time'],
                "day" => $row['day'],
                "day_name" => $row['day_name'],
                "subject" => $row['subject_teacher']['subject'],
                "teacher_first_name" => $row['subject_teacher']['teacher']['user']['first_name'],
                "teacher_last_name" => $row['subject_teacher']['teacher']['user']['last_name'],
            );
        }
        return $response;
    }
}
