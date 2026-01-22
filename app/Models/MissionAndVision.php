<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionAndVision extends Model
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
        'mission_and_vision_header',
        'mission_and_vision_title',
        'mission_and_vision_description',
        'mission_content',
        'vision_content',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Check if the mission and vision section is empty
     */
    public static function isMissionVisionSectionEmpty()
    {
        $record = self::first();

        if (!$record) {
            return true; // Table is completely empty
        }

        // Check if all mission and vision columns are null or empty
        return empty($record->mission_and_vision_header) &&
            empty($record->mission_and_vision_title) &&
            empty($record->mission_and_vision_description) &&
            empty($record->mission_content) &&
            empty($record->vision_content);
    }

    /**
     * Get or create the mission and vision record
     */
    public static function getOrCreateRecord()
    {
        $record = self::first();

        if (!$record) {
            // Create new record if table is empty
            $record = self::create([
                'mission_and_vision_header' => '',
                'mission_and_vision_title' => '',
                'mission_and_vision_description' => '',
                'mission_content' => '',
                'vision_content' => '',
            ]);
        }

        return $record;
    }
}