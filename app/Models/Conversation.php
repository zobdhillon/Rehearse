<?php

namespace App\Models;

use App\Models\Message;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;
    protected $fillable = [
        'scenario_id',
        'user_id',
        'scores',
        'status',
    ];

    protected $casts = [
        'scores' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function scenario()
    {
        return $this->belongsTo(Scenario::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
