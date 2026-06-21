<?php

namespace App\Models;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Ensure this says Scenario, NOT Conversation!
class Scenario extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'system_prompt',
        'user_role',
        'ai_role',
        'objectives',
        'category',
        'difficulty',
        'estimated_duration',
        'icon',
        'color',
        'is_active',
        'order',
    ];

    protected $casts = [
        'objectives' => 'array',
    ];

    /**
     * Get all of the conversations for the Scenario.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
