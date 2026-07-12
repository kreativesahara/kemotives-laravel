<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function searchCarProduct(Request $request)
    {
        try {
            $make = $request->query('make');
            $model = $request->query('model');
            $location = $request->query('location');
            $condition = $request->query('condition');
            $minPrice = $request->query('minPrice');
            $maxPrice = $request->query('maxPrice');
            $fuelType = $request->query('fuelType');
            $transmission = $request->query('transmission');
            $driveSystem = $request->query('driveSystem');

            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query = Car::where('is_active', 'true')->with('images');

            if ($make && !$model) {
                $query->where(function ($q) use ($make, $like) {
                    $q->where('make', $like, '%' . $make . '%')
                      ->orWhere('model', $like, '%' . $make . '%');
                });
            } elseif ($make && $model) {
                $query->where('make', $like, '%' . $make . '%')
                      ->where('model', $like, '%' . $model . '%');
            } elseif ($model && !$make) {
                $query->where('model', $like, '%' . $model . '%');
            }

            if ($location) {
                $query->where('view_location', $like, '%' . $location . '%');
            }

            if ($condition) {
                $query->where('car_condition', $like, '%' . $condition . '%');
            }

            if ($minPrice !== null && $minPrice !== '') {
                $query->where('price', '>=', (float) $minPrice);
            }

            if ($maxPrice !== null && $maxPrice !== '') {
                $query->where('price', '<=', (float) $maxPrice);
            }

            if ($fuelType) {
                $query->where('fuel_type', $like, '%' . $fuelType . '%');
            }

            if ($transmission) {
                $query->where('transmission', $like, '%' . $transmission . '%');
            }

            if ($driveSystem) {
                $query->where('drive_system', $like, '%' . $driveSystem . '%');
            }

            $cars = $query->get();

            if ($cars->isEmpty()) {
                return response()->json([]);
            }

            $formattedCars = $cars->map(function ($car) {
                $data = $car->formatForApi();
                // Match the legacy response where images is an array of objects
                $data['images'] = $car->images->map(function ($img) {
                    return ['image_url' => $img->image_url];
                })->toArray();
                return $data;
            });

            return response()->json($formattedCars)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error'], 500);
        }
    }
}
