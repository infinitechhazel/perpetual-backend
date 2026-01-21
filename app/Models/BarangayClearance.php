<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangayClearance extends Model
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
        'years_of_residency',
        'barangay',
        'purpose',
        'valid_id_path',
        'status',
        'remarks',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'years_of_residency' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateReferenceNumber()
    {
        $prefix = 'BC';
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        return "{$prefix}-{$year}-{$random}";
    }
}