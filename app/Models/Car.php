<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    protected $table = 'cars';

    protected $fillable = [
        'seller_id', 'make', 'model', 'slug', 'yom', 'engine_capacity', 'fuel_type',
        'transmission', 'drive_system', 'mileage', 'features', 'car_condition',
        'vehicle_registration_prefix', 'vehicle_registration_suffix', 'view_location',
        'price', 'category', 'is_active', 'status', 'cycle_count', 'cycle_count_history',
        'ai_grade', 'ai_score', 'ai_notes', 'views'
    ];

    protected $casts = [
        'is_active' => 'boolean', // Fix varchar issue
        'price' => 'float', // Fix text issue
        'cycle_count_history' => 'array',
        'yom' => 'integer',
        'cycle_count' => 'integer',
        'ai_score' => 'integer',
        'views' => 'integer',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'user_id');
    }

    public function images()
    {
        return $this->hasMany(CarImage::class, 'car_id');
    }

    /**
     * Map exactly to the Express JSON contract output shape.
     */
    public function formatForApi(): array
    {
        return [
            'id' => $this->id,
            'sellerId' => $this->seller_id,
            'make' => $this->make,
            'model' => $this->model,
            'slug' => $this->slug,
            'year' => $this->yom,
            'engineCapacity' => $this->engine_capacity,
            'fuelType' => $this->fuel_type,
            'transmission' => $this->transmission,
            'driveSystem' => $this->drive_system,
            'mileage' => $this->mileage,
            'features' => $this->features,
            'condition' => $this->car_condition,
            'vehicleRegPrefix' => $this->vehicle_registration_prefix,
            'vehicleRegSuffix' => $this->vehicle_registration_suffix,
            'location' => $this->view_location,
            'price' => $this->price,
            'category' => $this->category,
            'views' => $this->views,
            'isActive' => $this->getRawOriginal('is_active'), // Return the raw string 'true' or 'false'
            'status' => $this->status,
            'cycleCount' => $this->cycle_count,
            'cycleCountHistory' => $this->getRawOriginal('cycle_count_history'),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'aiGrade' => $this->ai_grade,
            'aiScore' => $this->ai_score,
            'aiNotes' => $this->ai_notes,
            'images' => $this->relationLoaded('images') 
                ? $this->images->pluck('image_url')->toArray() 
                : [],
        ];
    }
}
