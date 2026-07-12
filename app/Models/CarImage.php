<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarImage extends Model
{
    use HasFactory;

    protected $table = 'car_images';

    const UPDATED_AT = null;

    protected $fillable = [
        'car_id', 'image_url'
    ];

    public function car()
    {
        return $this->belongsTo(Car::class, 'car_id');
    }
}
