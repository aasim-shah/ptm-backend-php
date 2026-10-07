<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentFee extends Model
{
    use HasFactory;
    protected $hidden = ["created_at", "updated_at"];

    public function session_year(){
        return $this->belongsTo(SessionYear::class,'session_year_id');
    }

     //Getter Attributes
    public function getDueDateAttribute($value) {
        $data = getSettings('date_formate');
        return date($data['date_formate'] ?? 'd-m-Y' ,strtotime($value));
    }
}
