<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Accessory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FilterController extends Controller
{
    public function filterCarProducts(Request $request)
    {
        try {
            $make = $request->query('make');
            $model = $request->query('model');
            $category = $request->query('category');
            $location = $request->query('location');
            $yearFrom = $request->query('yearFrom');
            $yearTo = $request->query('yearTo');
            $priceMin = $request->query('priceMin');
            $priceMax = $request->query('priceMax');
            $fuelType = $request->query('fuelType');
            $transmission = $request->query('transmission');
            $mileageRange = $request->query('mileageRange');
            $condition = $request->query('condition');
            $driveSystem = $request->query('driveSystem');
            $engine_capacity = $request->query('engine_capacity');
            $features = $request->query('features');
            $search = $request->query('search');
            $isActive = $request->query('isActive');

            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query = Car::where('status', 'active')->has('images')->with('images');

            if ($make) $query->where('make', $like, "%{$make}%");
            if ($model) $query->where('model', $like, "%{$model}%");
            if ($location) $query->where('view_location', $like, "%{$location}%");

            if ($category) $query->where('category', (string) $category);
            if ($fuelType) $query->where('fuel_type', (string) $fuelType);
            if ($transmission) $query->where('transmission', (string) $transmission);
            if ($condition) $query->where('car_condition', (string) $condition);
            if ($driveSystem) $query->where('drive_system', (string) $driveSystem);

            if (is_numeric($yearFrom)) $query->where('yom', '>=', (int) $yearFrom);
            if (is_numeric($yearTo)) $query->where('yom', '<=', (int) $yearTo);

            if (is_numeric($priceMin)) $query->where('price', '>=', (float) $priceMin);
            if (is_numeric($priceMax)) $query->where('price', '<=', (float) $priceMax);

            if (is_numeric($engine_capacity)) $query->where('engine_capacity', (string) $engine_capacity);

            if ($mileageRange) {
                if (str_contains($mileageRange, '-')) {
                    $parts = explode('-', $mileageRange);
                    $min = trim($parts[0]);
                    $max = trim($parts[1]);
                    if (is_numeric($min) && is_numeric($max)) {
                        $query->whereBetween('mileage', [(float) $min, (float) $max]);
                    } elseif (is_numeric($min)) {
                        $query->where('mileage', '>=', (float) $min);
                    }
                } elseif (is_numeric($mileageRange)) {
                    $query->where('mileage', '<=', (float) $mileageRange);
                }
            }

            if ($features) {
                $featureArr = array_map('trim', explode(',', $features));
                $query->where(function ($q) use ($featureArr, $like) {
                    foreach ($featureArr as $f) {
                        $q->orWhere('features', $like, "%{$f}%");
                    }
                });
            }

            if ($search) {
                $query->where(function ($q) use ($search, $like) {
                    $q->orWhere('make', $like, "%{$search}%")
                      ->orWhere('model', $like, "%{$search}%")
                      ->orWhere('view_location', $like, "%{$search}%");
                });
            }

            if ($isActive !== null) {
                $query->where('is_active', (string) $isActive);
            } else {
                $query->where('is_active', 'true');
            }

            $cars = $query->orderByDesc('id')->get();

            if ($cars->isEmpty()) {
                return response()->json([]);
            }

            $formattedCars = $cars->map(function ($car) {
                $data = $car->formatForApi();
                $data['images'] = $car->images->map(function ($img) {
                    return ['image_url' => $img->image_url];
                })->toArray();
                return $data;
            });

            return response()->json($formattedCars);

        } catch (\Exception $e) {
            Log::error('Filter error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Server Error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ], 500);
        }
    }

    public function filterAccessoryProducts(Request $request)
    {
        try {
            $name = $request->query('name');
            $category = $request->query('category');
            $condition = $request->query('condition');
            $location = $request->query('location');
            $priceMin = $request->query('priceMin');
            $priceMax = $request->query('priceMax');
            $stock = $request->query('stock');
            $status = $request->query('status');
            $isActive = $request->query('isActive');
            $search = $request->query('search');

            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query = Accessory::query();

            if ($name) $query->where('name', $like, "%{$name}%");
            if ($category) $query->where('category', $like, "%{$category}%");
            if ($location) $query->where('location', $like, "%{$location}%");

            if ($condition) $query->where('condition', (string) $condition);
            if ($status) $query->where('status', (string) $status);
            if ($isActive) $query->where('is_active', (string) $isActive);

            if (is_numeric($priceMin)) $query->where('price', '>=', (float) $priceMin);
            if (is_numeric($priceMax)) $query->where('price', '<=', (float) $priceMax);

            if ($stock === 'available') {
                $query->where('stock', '>=', 1);
            } elseif (is_numeric($stock)) {
                $query->where('stock', '>=', (int) $stock);
            }

            if ($search) {
                $query->where(function ($q) use ($search, $like) {
                    $q->orWhere('name', $like, "%{$search}%")
                      ->orWhere('description', $like, "%{$search}%")
                      ->orWhere('category', $like, "%{$search}%");
                });
            }

            if (!$isActive) $query->where('is_active', 'true');
            if (!$status) $query->where('status', 'active');

            $accessories = $query->orderByDesc('id')->get();

            if ($accessories->isEmpty()) {
                return response()->json([]);
            }

            return response()->json($accessories);

        } catch (\Exception $e) {
            Log::error('Accessory filter error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Server Error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ], 500);
        }
    }
}
