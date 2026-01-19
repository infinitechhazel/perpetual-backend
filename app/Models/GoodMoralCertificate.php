<?php
// app/Models/GoodMoralCertificate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GoodMoralCertificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'reference_number',
        'full_name',
        'email',
        'phone',
        'address',
        'birth_date',
        'age',
        'sex',
        'civil_status',
        'barangay',
        'years_of_residency',
        'occupation',
        'purpose',
        'valid_id_path',
        'proof_of_residency_path',
        'status',
        'remarks',
        'approved_at',
        'released_at',
        'approved_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected $appends = ['status_badge', 'valid_id_url', 'proof_of_residency_url'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return [
            'pending' => ['text' => 'Pending', 'color' => 'yellow'],
            'approved' => ['text' => 'Approved', 'color' => 'green'],
            'rejected' => ['text' => 'Rejected', 'color' => 'red'],
            'released' => ['text' => 'Released', 'color' => 'blue'],
        ][$this->status] ?? ['text' => 'Unknown', 'color' => 'gray'];
    }

    public function getValidIdUrlAttribute()
    {
        return $this->valid_id_path ? url($this->valid_id_path) : null;
    }

    public function getProofOfResidencyUrlAttribute()
    {
        return $this->proof_of_residency_path ? url($this->proof_of_residency_path) : null;
    }

    // Boot method for auto-generating reference number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($certificate) {
            if (empty($certificate->reference_number)) {
                $certificate->reference_number = self::generateReferenceNumber();
            }
        });
    }

    // Generate unique reference number
    public static function generateReferenceNumber()
    {
        do {
            $referenceNumber = 'GMC-' . date('Y') . '-' . strtoupper(Str::random(8));
        } while (self::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    public function scopeByBarangay($query, $barangay)
    {
        return $query->where('barangay', $barangay);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('reference_number', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%");
        });
    }
}