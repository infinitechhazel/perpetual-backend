<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'address',
        'fraternity_number',
        'status',
        'role',
        'rejection_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $visible = [
        'id',
        'name',
        'email',
        'phone_number',
        'address',
        'fraternity_number',
        'status',
        'role',
        'rejection_reason',
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Role check methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'member';
    }

    // Add isMember() as an alias for isUser()
    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    // Status check methods
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isDeactivated(): bool
    {
        return $this->status === 'deactivated';
    }

    // Query scopes for status
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDeactivated($query)
    {
        return $query->where('status', 'deactivated');
    }

    // Query scopes for role
    public function scopeUsers($query)
    {
        return $query->where('role', 'member');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Relationship: User has many business partners
     */
    public function businessPartners()
    {
        return $this->hasMany(BusinessPartner::class);
    }
}