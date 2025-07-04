<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    Use HasFactory;

    public $table = 'messages';
    protected  $fillable = ['id', 'user_id', 'text'];

    public function user() : BelongsTo
    {
        return  $this->belongsTo(User :: class, 'user_id' );
    }
public function getTimeAttribute(): String
{
return  date(
    "d-m-Y-H-i-s" , strtotime($this->attributes['created_at']));

}

}
