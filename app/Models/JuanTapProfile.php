<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JuanTapProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_url',
        'qr_code',
        'status',
        'subscription',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
