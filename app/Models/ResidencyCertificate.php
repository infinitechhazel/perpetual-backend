<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResidencyCertificate extends Model
{
    use HasFactory;

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
        'rejection_reason',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'years_of_residency' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'valid_id_url',
        'proof_of_residency_url',
    ];

    /**
     * Boot method to generate reference number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($certificate) {
            if (empty($certificate->reference_number)) {
                $certificate->reference_number = self::generateReferenceNumber();
            }
        });
    }

    /**
     * Generate unique reference number
     */
    public static function generateReferenceNumber(): string
    {
        do {
            $referenceNumber = 'RC-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }

    /**
     * Get the user that owns the certificate
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who processed the certificate
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the full URL for valid ID
     */
    public function getValidIdUrlAttribute()
    {
        if ($this->valid_id_path) {
            return url($this->valid_id_path);
        }
        return null;
    }

    /**
     * Get the full URL for proof of residency
     */
    public function getProofOfResidencyUrlAttribute()
    {
        if ($this->proof_of_residency_path) {
            return url($this->proof_of_residency_path);
        }
        return null;
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        if ($status && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope to search certificates
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('barangay', 'like', "%{$search}%");
            });
        }
        return $query;
    }

    /**
     * Scope to get recent certificates
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Check if certificate is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if certificate is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if certificate is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}