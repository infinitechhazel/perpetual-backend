<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BarangayBlotter extends Model
{
    use HasFactory;

    protected $table = 'barangay_blotters';

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'age',
        'gender',
        'address',
        'contact_number',
        'incident_type',
        'incident_date',
        'incident_time',
        'incident_location',
        'narrative',
        'complaint_against',
        'witness1_name',
        'witness1_contact',
        'witness2_name',
        'witness2_contact',
        'status',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
