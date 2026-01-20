<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegitimacyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'alias',
        'chapter',
        'position',
        'fraternity_number',
        'status',
        'admin_note',
        'signatory_name',
        'approved_at',
    ];

    // Optional: cast approved_at to datetime
    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // Relationship to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
