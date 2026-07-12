<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $table = 'sellers';

    protected $fillable = [
        'user_id', 'username', 'account_type', 'contact', 'place', 'image_url',
        'has_financing', 'accepts_trade_in', 'has_subscription'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cars()
    {
        return $this->hasMany(Car::class, 'seller_id', 'user_id');
    }
}
