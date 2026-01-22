<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signatory extends Model
{
    protected $fillable = ['legitimacy_request_id', 'name', 'role',  'signed_date', 'signature_url'];

    public function legitimacyRequest()
    {
        return $this->belongsTo(LegitimacyRequest::class);
    }
}
