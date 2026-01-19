<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuildingPermit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'reference_number',
        'project_type',
        'project_scope',
        'project_description',
        'lot_area',
        'floor_area',
        'number_of_floors',
        'estimated_cost',
        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_address',
        'property_address',
        'barangay',
        'building_plans_path',
        'land_title_path',
        'status',
        'rejection_reason',
        'approved_at',
        'completed_at',
    ];

    protected $casts = [
        'lot_area' => 'decimal:2',
        'floor_area' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['building_plans_url', 'land_title_url'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($permit) {
            if (empty($permit->reference_number)) {
                $permit->reference_number = 'BP-' . strtoupper(uniqid());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessor for full URL to building plans
    public function getBuildingPlansUrlAttribute()
    {
        return $this->building_plans_path 
            ? url($this->building_plans_path) 
            : null;
    }

    // Accessor for full URL to land title
    public function getLandTitleUrlAttribute()
    {
        return $this->land_title_path 
            ? url($this->land_title_path) 
            : null;
    }
}
