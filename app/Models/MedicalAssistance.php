<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalAssistance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'full_name',
        'user_id',
        'email',
        'phone',
        'address',
        'birth_date',
        'age',
        'sex',
        'diagnosis',
        'hospital_name',
        'doctor_name',
        'estimated_cost',
        'monthly_income',
        'assistance_amount_requested',
        'supporting_documents',
        'status',
        'remarks',
        'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'estimated_cost' => 'decimal:2',
        'monthly_income' => 'decimal:2',
        'assistance_amount_requested' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the medical assistance application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a unique reference number
     */
    public static function generateReferenceNumber(): string
    {
        do {
            $referenceNumber = 'MA-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));
        } while (self::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }

    /**
     * Get the status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'gray',
            'processing' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'completed' => 'purple',
            default => 'gray',
        };
    }

    /**
     * Get the formatted estimated cost
     */
    public function getFormattedEstimatedCostAttribute(): string
    {
        return '₱' . number_format($this->estimated_cost, 2);
    }

    /**
     * Get the formatted monthly income
     */
    public function getFormattedMonthlyIncomeAttribute(): string
    {
        return '₱' . number_format($this->monthly_income, 2);
    }

    /**
     * Get the formatted assistance amount
     */
    public function getFormattedAssistanceAmountAttribute(): string
    {
        return '₱' . number_format($this->assistance_amount_requested, 2);
    }
}