<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'website_link',
        'photo',
        'description',
        'category',
        'status',
        'admin_note',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}