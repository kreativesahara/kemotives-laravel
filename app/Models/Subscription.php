<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id', 'plan_name', 'amount', 'currency', 'status',
        'managed_by', 'auto_renewal', 'plan_type', 'start_date', 'end_date'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'auto_renewal' => 'boolean',
    ];
}
