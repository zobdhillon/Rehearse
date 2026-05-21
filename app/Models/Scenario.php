<?php

namespace App\Models;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;

class Scenario extends Model
{
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
