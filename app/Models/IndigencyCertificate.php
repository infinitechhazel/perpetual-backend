<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndigencyCertificate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'monthly_income',
        'number_of_dependents',
        'purpose',
        'reason_for_indigency',
        'valid_id_path',
        'supporting_document_path',
        'status',
        'admin_remarks',
        'approved_at',
        'rejected_at',
        'released_at',
        'processed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'years_of_residency' => 'integer',
        'number_of_dependents' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the indigency certificate.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who processed the certificate.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReferenceNumber(): string
    {
        do {
            $referenceNumber = 'IC-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (self::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }

    /**
     * Scope a query to only include certificates with a specific status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to search certificates.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('reference_number', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%");
        });
    }

    /**
     * Check if the certificate is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the certificate is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the certificate is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the certificate is released.
     */
    public function isReleased(): bool
    {
        return $this->status === 'released';
    }
}