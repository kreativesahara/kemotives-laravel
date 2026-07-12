<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeletedSeller extends Model
{
    use HasFactory;

    protected $table = 'deleted_sellers';
    
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false; // Because deleted_at is custom mapped in DB

    protected $fillable = [
        'user_id', 'deleted_at'
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}
