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
    ];

    public function toArray()
    {
        $array = parent::toArray();
        // The Express app maps camelCase keys, let's map snake_case to camelCase
        return [
            'id' => $array['id'],
            'userId' => $array['user_id'],
            'name' => $array['name'],
            'description' => $array['description'],
            'category' => $array['category'],
            'slug' => $array['slug'],
            'condition' => $array['condition'],
            'location' => $array['location'],
            'price' => $array['price'],
            'stock' => $array['stock'],
            'imageUrls' => $array['image_urls'] ?? [],
            'views' => $array['views'],
            'isActive' => $array['is_active'],
            'status' => $array['status'],
            'cycleCount' => $array['cycle_count'],
            'cycleCountHistory' => $array['cycle_count_history'],
            'createdAt' => $array['created_at'] ?? null,
            'updatedAt' => $array['updated_at'] ?? null,
        ];
    }
}
