<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Objective extends Model
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
        'objectives_header',
        'objectives_title',
        'objectives_description',
        'objectives_card_title',
        'objectives_card_content',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'objectives_card_title' => 'array',
        'objectives_card_content' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Check if the objectives section is empty
     */
    public static function isObjectivesSectionEmpty()
    {
        $record = self::first();

        if (!$record) {
            return true;
        }

        return empty($record->objectives_header) &&
            empty($record->objectives_title) &&
            empty($record->objectives_description) &&
            empty($record->objectives_card_title) &&
            empty($record->objectives_card_content);
    }

    /**
     * Get or create the objectives record
     */
    public static function getOrCreateRecord()
    {
        $record = self::first();

        if (!$record) {
            $record = self::create([
                'objectives_header' => '',
                'objectives_title' => '',
                'objectives_description' => '',
                'objectives_card_title' => [],
                'objectives_card_content' => [],
            ]);
        }

        return $record;
    }
}