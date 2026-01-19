<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPermit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'business_category',
        'business_category_other',
        'business_description',
        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_address',
        'business_address',
        'barangay',
        'lot_number',
        'floor_area',
        'status',
        'rejection_reason',
        'permit_number',
        'approved_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'approved_at' => 'date',
        'expires_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the business permit.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a unique permit number
     */
    public static function generatePermitNumber(): string
    {
        $year = date('Y');
        $lastPermit = self::whereYear('created_at', $year)
            ->whereNotNull('permit_number')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPermit && $lastPermit->permit_number) {
            $lastNumber = (int) substr($lastPermit->permit_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'BP-' . $year . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Scope a query to only include permits for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include permits with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}