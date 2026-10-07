<?php

namespace App\Rules;

use App\Models\Lesson;
use App\Models\LessonTopic;
use Illuminate\Contracts\Validation\Rule;

class uniqueTopicInLesson implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */

    public function __construct($lesson_id, $topic_id = NULL)
    {
        $this->lesson_id = $lesson_id;
        $this->topic_id = $topic_id;
    }
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->topic_id == NULL) {
            $count = LessonTopic::where('name', $value)->where('lesson_id', $this->lesson_id)->count();
            if ($count == 0) {
                return true;
            } else {
                return false;
            }
        } else {
            $count = LessonTopic::where('name', $value)->where('lesson_id', $this->lesson_id)->whereNot('id',$this->topic_id)->count();
            if ($count == 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('topic_alredy_exists');
    }
}
