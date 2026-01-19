<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmbulanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'request_id',
        'name',
        'phone',
        'address',
        'emergency',
        'notes',
        'latitude',
        'longitude',
        'status',
        'requested_at',
        'dispatched_at',
        'arrived_at',
        'completed_at',
        'estimated_arrival',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ambulanceRequest) {
            if (empty($ambulanceRequest->request_id)) {
                $ambulanceRequest->request_id = 'AMB-' . strtoupper(uniqid());
            }
            if (empty($ambulanceRequest->requested_at)) {
                $ambulanceRequest->requested_at = now();
            }
        });
    }
}