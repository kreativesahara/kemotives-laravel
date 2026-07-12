<?php

namespace App\Repositories;

use App\Models\Car;

class VehicleRepository
{
    /**
     * Get all active products, eager loading images to prevent N+1 queries.
     */
    public function getAllActive(int $limit = 100, string $sortBy = 'createdAt', string $order = 'desc')
    {
        $sortMap = [
            'createdAt' => 'created_at',
            'id' => 'id',
            'price' => 'price',
            'year' => 'yom'
        ];
        
        $sortColumn = $sortMap[$sortBy] ?? 'created_at';

        return Car::with(['images'])
            ->where('is_active', "true")
            ->orderBy($sortColumn, $order)
            ->limit($limit)
            ->get();
    }

    /**
     * Get a single product by slug, eager loading seller and images.
     */
    public function getBySlug(string $slug)
    {
        return Car::with(['images', 'seller'])
            ->where('slug', $slug)
            ->where('is_active', "true")
            ->first();
    }

    /**
     * Increment views atomically.
     */
    public function incrementViews(string $slug)
    {
        $car = Car::where('slug', $slug)->first();
        if ($car) {
            $car->increment('views');
            return $car->views;
        }
        return null;
    }

    public function create(array $data)
    {
        return Car::create($data);
    }

    public function findById(int $id)
    {
        return Car::find($id);
    }

    public function update(int $id, array $data)
    {
        $car = $this->findById($id);
        if ($car) {
            $car->update($data);
        }
        return $car;
    }

    public function delete(int $id)
    {
        return Car::destroy($id);
    }

    /**
     * Get all products for a specific seller (both active and inactive)
     */
    public function getSellerProducts(int $sellerId, int $limit = 100, string $sortBy = 'createdAt', string $order = 'desc')
    {
        $sortMap = [
            'createdAt' => 'created_at',
            'id' => 'id',
            'price' => 'price',
            'year' => 'yom'
        ];
        
        $sortColumn = $sortMap[$sortBy] ?? 'created_at';

        return Car::with(['images'])
            ->where('seller_id', $sellerId)
            ->orderBy($sortColumn, $order)
            ->limit($limit)
            ->get();
    }
}
