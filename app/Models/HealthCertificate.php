<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthCertificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'user_id',
        'full_name',
        'email',
        'phone',
        'address',
        'birth_date',
        'age',
        'sex',
        'purpose',
        'has_allergies',
        'allergies',
        'has_medications',
        'medications',
        'has_conditions',
        'conditions',
        'status',
        'remarks',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'has_allergies' => 'boolean',
        'has_medications' => 'boolean',
        'has_conditions' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function generateReferenceNumber(): string
    {
        do {
            $referenceNumber = 'HC-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (self::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}