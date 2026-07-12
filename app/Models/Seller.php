<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $table = 'sellers';

    const UPDATED_AT = null;

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

    public function toArray()
    {
        $array = parent::toArray();
        return [
            'id' => $array['id'] ?? null,
            'userId' => $array['user_id'] ?? null,
            'username' => $array['username'] ?? null,
            'accountType' => $array['account_type'] ?? null,
            'contact' => $array['contact'] ?? null,
            'place' => $array['place'] ?? null,
            'imageUrl' => $array['image_url'] ?? null,
            'hasFinancing' => $array['has_financing'] ?? false,
            'acceptsTradeIn' => $array['accepts_trade_in'] ?? false,
            'hasSubscription' => $array['has_subscription'] ?? false,
            'createdAt' => $array['created_at'] ?? null,
        ];
    }
}
