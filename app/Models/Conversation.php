<?php

namespace App\Models;

use App\Models\Message;
use App\Models\Scenario;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    public function scenario()
    {
        return $this->belongsTo(Scenario::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
