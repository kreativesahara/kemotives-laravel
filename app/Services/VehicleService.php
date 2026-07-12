<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Subscription;
use Illuminate\Support\Str;

class VehicleService
{
    const PLAN_LISTING_LIMITS = [
        'starter' => 10,
        'basic' => 25,
        'growth' => 50,
        'enterprise' => 100,
        'custom' => PHP_INT_MAX,
    ];

    public function checkSellerListingLimit(int $sellerId): array
    {
        $activeSub = Subscription::where('user_id', $sellerId)
            ->where('status', 'active')
            ->first();

        $planName = $activeSub ? strtolower($activeSub->plan_name) : 'starter';
        $maxListings = self::PLAN_LISTING_LIMITS[$planName] ?? 10;

        $currentListingsCount = Car::where('seller_id', $sellerId)
            ->where('is_active', "true")
            ->count();

        return [
            'planName' => $planName,
            'maxListings' => $maxListings,
            'currentListingsCount' => $currentListingsCount,
            'canCreateMore' => $currentListingsCount < $maxListings
        ];
    }

    public function generateSlug(string $make, string $model, int $year, string $location): string
    {
        $random = Str::random(6);
        $slugString = "{$make}-{$model}-{$year}-{$location}-{$random}";
        return Str::slug($slugString);
    }
}
