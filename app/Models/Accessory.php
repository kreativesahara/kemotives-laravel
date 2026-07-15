<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accessory extends Model
{
    public $timestamps = false; // We manage createdAt/updatedAt manually or map them
    
    protected $fillable = [
        'user_id', 'name', 'description', 'category', 'slug', 'condition', 
        'location', 'price', 'stock', 'image_urls', 'views', 'is_active', 
        'status', 'cycle_count', 'cycle_count_history', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'image_urls' => 'array',
        'price' => 'float',
        'stock' => 'integer',
        'views' => 'integer',
        'cycle_count' => 'integer',
        'is_active' => 'string',
    ];

    public function toArray()
    {
        $array = parent::toArray();
        // The Express app maps camelCase keys, let's map snake_case to camelCase
        return [
            'id' => $array['id'],
            'userId' => $array['user_id'] ?? null,
            'name' => $array['name'] ?? null,
            'description' => $array['description'] ?? null,
            'category' => $array['category'] ?? null,
            'slug' => $array['slug'] ?? null,
            'condition' => $array['condition'] ?? null,
            'location' => $array['location'] ?? null,
            'price' => $array['price'] ?? 0,
            'stock' => $array['stock'] ?? 0,
            'imageUrls' => $array['image_urls'] ?? [],
            'views' => $array['views'] ?? 0,
            'isActive' => $array['is_active'] ?? 'true',
            'status' => $array['status'] ?? 'active',
            'cycleCount' => $array['cycle_count'] ?? 0,
            'cycleCountHistory' => $array['cycle_count_history'] ?? null,
            'createdAt' => $array['created_at'] ?? null,
            'updatedAt' => $array['updated_at'] ?? null,
        ];
    }
}
