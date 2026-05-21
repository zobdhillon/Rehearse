<?php

namespace App\Models;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
