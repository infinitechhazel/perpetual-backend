<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'about_section';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'community_header',
        'community_title',
        'community_content',
        'community_list',
        'community_card_icon',
        'community_card_number',
        'community_card_category',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
