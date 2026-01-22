<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficeContact extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'office_contact';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'office_location',
        'phone_number',
        'email',
        'office_hours',
        'map_link',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Check if the office contact section is empty
     */
    public static function isOfficeContactSectionEmpty()
    {
        $record = self::first();

        if (!$record) {
            return true;
        }

        return empty($record->office_location) &&
            empty($record->phone_number) &&
            empty($record->email) &&
            empty($record->office_hours) &&
            empty($record->map_link);
    }

    /**
     * Get or create the office contact record
     */
    public static function getOrCreateRecord()
    {
        $record = self::first();

        if (!$record) {
            $record = self::create([
                'office_location' => '',
                'phone_number' => '',
                'email' => '',
                'office_hours' => '',
                'map_link' => '',
            ]);
        }

        return $record;
    }
}