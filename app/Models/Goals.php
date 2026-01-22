<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goals extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'about_section';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'goals_header',
        'goals_title',
        'goals_description',
        'goals_card_icon',
        'goals_card_title',
        'goals_card_content',
        'goals_card_list',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Check if the goals section is empty (all goals columns are null/empty)
     */
    public static function isGoalsSectionEmpty()
    {
        $record = self::first();
        
        if (!$record) {
            return true; // Table is completely empty
        }

        // Check if all goals columns are null or empty
        return empty($record->goals_header) &&
               empty($record->goals_title) &&
               empty($record->goals_description) &&
               empty($record->goals_card_icon) &&
               empty($record->goals_card_title) &&
               empty($record->goals_card_content) &&
               empty($record->goals_card_list);
    }

    /**
     * Get or create the goals record
     */
    public static function getOrCreateRecord()
    {
        $record = self::first();
        
        if (!$record) {
            // Create new record if table is empty
            $record = self::create([
                'goals_header' => '',
                'goals_title' => '',
                'goals_description' => '',
                'goals_card_icon' => json_encode([]),
                'goals_card_title' => json_encode([]),
                'goals_card_content' => json_encode([]),
                'goals_card_list' => json_encode([]),
            ]);
        }
        
        return $record;
    }
}