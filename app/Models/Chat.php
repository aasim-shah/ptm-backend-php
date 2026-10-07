<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property integer $user_id
 * @property string $message
 * @property string $created_at
 * @property string $updated_at
 * @property User $user
 */
class Chat extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'chat';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     * 
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['user_id','new_message_count', 'message', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
    public function receiver()
    {
        return $this->belongsTo('App\Models\User','to_user_id','id');
    }

    public function getMessageAttribute(){
        $message = json_decode($this->attributes['message'],true);
        $index = 0;
//        foreach ($message as $m){
//            $fileName = $m['file'] ?? null;
//            $image = false;
//            $message[$index]['is_image'] = $image;
//            if($fileName){
//                $file = url(Storage::url("chat/files/".$fileName));
//                $ext = explode('.',$fileName);
//                if(in_array($ext[1],['png','jpg','jpeg','svg','webp','mp4','gif'])){
//                    $image = true;
//                }
//                $message[$index]['file_url'] = $file;
//                $message[$index]['is_image'] = $image;
//            }
//            $index++;
//        }
//        Log::info($message);
        return $message;
    }
}
