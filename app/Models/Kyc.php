<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    use HasFactory;

    protected $table = 'kyc';

    protected $fillable = [
        'seller_id',
        'full_name',
        'kra_pin',
        'registration_date',
        'permit_number',
        'address',
        'id_document_url',
        'id_document_public_id',
        'selfie_url',
        'selfie_public_id',
        'status',
        'notes',
        'verified_by',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
