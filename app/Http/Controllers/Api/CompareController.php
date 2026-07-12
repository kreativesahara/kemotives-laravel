<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Accessory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompareController extends Controller
{
    private const MAX_COMPARE_ITEMS = 4;

    public function getCompareItems(Request $request)
    {
        try {
            $type = $request->query('type');
            $ids = $request->query('ids');

            if (!$type || !in_array($type, ['vehicles', 'accessories'])) {
                return response()->json(['message' => 'Invalid type. Must be "vehicles" or "accessories".'], 400);
            }

            if (!$ids || !is_string($ids)) {
                return response()->json(['message' => 'IDs are required as a comma-separated string.'], 400);
            }

            $parsedIds = array_filter(
                array_map(function($id) { return (int) trim($id); }, explode(',', $ids)),
                function($id) { return $id > 0; }
            );

            if (empty($parsedIds)) {
                return response()->json(['message' => 'At least one valid ID is required.'], 400);
            }

            if (count($parsedIds) > self::MAX_COMPARE_ITEMS) {
                return response()->json(['message' => 'Maximum ' . self::MAX_COMPARE_ITEMS . ' items can be compared at once.'], 400);
            }

            if ($type === 'vehicles') {
                $cars = Car::whereIn('id', $parsedIds)->with('images')->get();

                if ($cars->isEmpty()) {
                    return response()->json(['message' => 'No vehicles found for the given IDs.'], 204);
                }

                $formattedCars = $cars->map(function ($car) {
                    $data = $car->formatForApi();
                    $data['images'] = $car->images->pluck('image_url')->toArray();
                    return $data;
                });

                return response()->json($formattedCars);

            } else {
                $items = Accessory::whereIn('id', $parsedIds)->get();

                if ($items->isEmpty()) {
                    return response()->json(['message' => 'No accessories found for the given IDs.'], 204);
                }

                return response()->json($items);
            }

        } catch (\Exception $e) {
            Log::error('Error fetching compare items: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }
}
