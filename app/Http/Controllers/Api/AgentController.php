<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
        'infiniti', 'genesis', 'lincoln', 'gmc', 'hummer'
    ];

    private const KNOWN_MODELS = [
        'toyota' => ['corolla', 'camry', 'rav4', 'highlander', 'prado', 'land cruiser', 'hilux', 'harrier', 'vitz', 'fielder', 'axio', 'premio', 'allion', 'wish', 'noah', 'voxy', 'alphard', 'fortuner', 'mark x', 'crown', 'c-hr', 'yaris', 'supra', 'tundra', 'tacoma', 'rush', 'avanza'],
        'honda' => ['civic', 'accord', 'cr-v', 'hr-v', 'fit', 'jazz', 'vezel', 'pilot', 'odyssey', 'insight', 'stream', 'freed', 'stepwagon'],
        'nissan' => ['note', 'x-trail', 'juke', 'qashqai', 'navara', 'patrol', 'serena', 'leaf', 'skyline', 'gt-r', 'murano', 'pathfinder', 'march', 'tiida', 'bluebird', 'teana', 'elgrand'],
        'mazda' => ['demio', 'axela', 'atenza', 'cx-3', 'cx-5', 'cx-7', 'cx-9', 'mx-5', 'mazda2', 'mazda3', 'mazda6'],
        'subaru' => ['impreza', 'forester', 'outback', 'legacy', 'xv', 'wrx', 'brz', 'levorg', 'crosstrek'],
        'bmw' => ['x1', 'x3', 'x5', 'x6', 'x7', '3 series', '5 series', '7 series', 'm3', 'm5', 'i3', 'i8'],
        'mercedes' => ['c-class', 'e-class', 's-class', 'a-class', 'b-class', 'gla', 'glc', 'gle', 'gls', 'g-class', 'amg'],
        'volkswagen' => ['golf', 'polo', 'passat', 'tiguan', 'touareg', 'jetta', 'arteon', 'atlas', 't-roc'],
        'hyundai' => ['tucson', 'santa fe', 'elantra', 'sonata', 'creta', 'venue', 'kona', 'palisade', 'ioniq'],
        'kia' => ['sportage', 'sorento', 'seltos', 'carnival', 'rio', 'cerato', 'picanto', 'stonic', 'niro'],
        'ford' => ['ranger', 'everest', 'escape', 'explorer', 'focus', 'fiesta', 'mustang', 'bronco', 'f-150'],
        'land rover' => ['defender', 'discovery', 'range rover', 'evoque', 'velar', 'freelander', 'sport'],
    ];

    private const KNOWN_LOCATIONS = [
        'nairobi', 'mombasa', 'kisumu', 'nakuru', 'eldoret', 'thika', 'malindi',
        'nanyuki', 'nyeri', 'meru', 'machakos', 'kitale', 'kakamega', 'kiambu',
        'naivasha', 'limuru', 'rongai', 'kajiado', 'garissa', 'lamu', 'kilifi',
        'westlands', 'karen', 'lavington', 'kilimani', 'ngong', 'langata',
        'industrial area', 'south b', 'south c', 'eastleigh', 'parklands'
    ];

    private const FUEL_TYPES = ['petrol', 'diesel', 'electric', 'hybrid', 'plug-in hybrid', 'lpg'];
    private const TRANSMISSIONS = ['automatic', 'manual', 'cvt', 'semi-automatic', 'tiptronic'];
    private const DRIVE_SYSTEMS = ['2wd', '4wd', 'awd', 'fwd', 'rwd', '4x4'];
    private const CONDITIONS = ['new', 'used', 'certified', 'foreign used', 'locally used', 'kenya new'];
    private const CATEGORIES = ['sedan', 'suv', 'hatchback', 'pickup', 'truck', 'van', 'wagon', 'coupe', 'convertible', 'crossover', 'bus', 'minivan', 'sports', 'luxury', 'compact'];

    private const INTENT_MAP = [
        'search' => ['find', 'show', 'search', 'looking', 'want', 'need', 'get', 'browse', 'list', 'display', 'any', 'give me', 'what'],
        'cheap' => ['cheap', 'affordable', 'budget', 'low price', 'under', 'below', 'less than', 'inexpensive', 'economical', 'value'],
        'expensive' => ['expensive', 'luxury', 'premium', 'high-end', 'above', 'over', 'more than', 'upscale'],
        'compare' => ['compare', 'versus', 'vs', 'or', 'between', 'difference'],
        'recommend' => ['recommend', 'suggest', 'best', 'top', 'popular', 'reliable', 'good'],
        'family' => ['family', 'spacious', 'roomy', 'kids', 'children', 'big'],
        'fuel_efficient' => ['fuel efficient', 'fuel economy', 'economical', 'low fuel', 'saves fuel', 'mpg', 'mileage', 'fuel saver'],
        'offroad' => ['offroad', 'off-road', 'rugged', 'terrain', 'bush', 'safari', 'adventure', '4x4'],
    ];

    private function getAvailableStats()
    {
        try {
            $makes = Car::where('is_active', 'true')
                ->where('status', 'active')
                ->groupBy('make')
                ->limit(10)
                ->pluck('make')
                ->toArray();
                
            $categories = Car::where('is_active', 'true')
                ->where('status', 'active')
                ->groupBy('category')
                ->limit(10)
                ->pluck('category')
                ->toArray();

            return [
                'makes' => $makes,
                'categories' => $categories
            ];
        } catch (\Exception $e) {
            return [
                'makes' => ['Toyota', 'Nissan', 'Honda', 'Subaru', 'Mazda'],
                'categories' => ['SUV', 'Sedan', 'Hatchback']
            ];
        }
    }

    private function parseNaturalLanguage($query)
    {
        $lower = trim(strtolower($query));
        $words = preg_split('/\s+/', $lower);

        $result = [
            'intent' => 'search',
            'make' => null,
            'model' => null,
            'location' => null,
            'condition' => null,
            'fuelType' => null,
            'transmission' => null,
            'driveSystem' => null,
            'category' => null,
            'priceMin' => null,
            'priceMax' => null,
            'yearMin' => null,
            'yearMax' => null,
            'keywords' => [],
            'originalQuery' => $query,
        ];

        foreach (self::INTENT_MAP as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $result['intent'] = $intent;
                    break 2;
                }
            }
        }

        foreach (self::KNOWN_MAKES as $make) {
            if (str_contains($lower, $make)) {
                $result['make'] = $make === 'vw' ? 'volkswagen' : ($make === 'range rover' ? 'land rover' : $make);
                break;
            }
        }

        if ($result['make']) {
            $normalizedMake = $result['make'] === 'mercedes-benz' ? 'mercedes' : $result['make'];
            $models = self::KNOWN_MODELS[$normalizedMake] ?? [];
            foreach ($models as $model) {
                if (str_contains($lower, $model)) {
                    $result['model'] = $model;
                    break;
                }
            }
        } else {
            foreach (self::KNOWN_MODELS as $make => $models) {
                foreach ($models as $model) {
                    if (str_contains($lower, $model) && strlen($model) > 2) {
                        $result['make'] = $make;
                        $result['model'] = $model;
                        break 2;
                    }
                }
            }
        }

        foreach (self::KNOWN_LOCATIONS as $loc) {
            if (str_contains($lower, $loc)) {
                $result['location'] = $loc;
                break;
            }
        }

        foreach (self::FUEL_TYPES as $fuel) {
            if (str_contains($lower, $fuel)) {
                $result['fuelType'] = $fuel;
                break;
            }
        }

        foreach (self::TRANSMISSIONS as $trans) {
            if (str_contains($lower, $trans)) {
                $result['transmission'] = $trans;
                break;
            }
        }

        foreach (self::DRIVE_SYSTEMS as $drive) {
            if (str_contains($lower, $drive)) {
                $result['driveSystem'] = $drive;
                break;
            }
        }

        foreach (self::CONDITIONS as $cond) {
            if (str_contains($lower, $cond)) {
                $result['condition'] = $cond;
                break;
            }
        }

        foreach (self::CATEGORIES as $cat) {
            if (str_contains($lower, $cat)) {
                $result['category'] = $cat;
                break;
            }
        }

        if (!$result['category']) {
            if ($result['intent'] === 'family' || str_contains($lower, 'spacious')) {
                $result['category'] = 'suv';
            }
            if ($result['intent'] === 'offroad') {
                $result['driveSystem'] = $result['driveSystem'] ?? '4wd';
            }
        }

        $pricePatterns = [
            '/(?:under|below|less than|max|up to|within)\s*([\d,.]+)\s*(m(?:illion)?|k|lakh)?/i',
            '/(?:above|over|more than|min|from|starting)\s*([\d,.]+)\s*(m(?:illion)?|k|lakh)?/i',
            '/(?:between|from)\s*([\d,.]+)\s*(m(?:illion)?|k)?\s*(?:and|to|-)\s*([\d,.]+)\s*(m(?:illion)?|k)?/i',
            '/([\d,.]+)\s*(m(?:illion)?|k)?\s*(?:-|to)\s*([\d,.]+)\s*(m(?:illion)?|k)?/i',
            '/(?:budget\s*(?:of|is|:)?\s*)([\d,.]+)\s*(m(?:illion)?|k)?/i',
        ];

        $parsePriceValue = function($numStr, $suffix = null) use ($lower) {
            $val = (float) str_replace(',', '', $numStr);
            if ($suffix) {
                $s = strtolower($suffix);
                if (str_starts_with($s, 'm')) $val *= 1000000;
                elseif ($s === 'k') $val *= 1000;
                elseif ($s === 'lakh') $val *= 100000;
            }
            if ($val > 0 && $val < 100 && !$suffix) {
                if (str_contains($lower, 'million') || preg_match('/\bm\b/i', $lower)) {
                    $val *= 1000000;
                }
            }
            return $val;
        };

        if (preg_match($pricePatterns[2], $lower, $matches) || preg_match($pricePatterns[3], $lower, $matches)) {
            if (isset($matches[3])) {
                $result['priceMin'] = $parsePriceValue($matches[1], $matches[2] ?? null);
                $result['priceMax'] = $parsePriceValue($matches[3], $matches[4] ?? null);
            }
        } else {
            if (preg_match($pricePatterns[0], $lower, $matches)) {
                $result['priceMax'] = $parsePriceValue($matches[1], $matches[2] ?? null);
            }
            if (preg_match($pricePatterns[1], $lower, $matches)) {
                $result['priceMin'] = $parsePriceValue($matches[1], $matches[2] ?? null);
            }
            if (preg_match($pricePatterns[4], $lower, $matches) && !$result['priceMax']) {
                $result['priceMax'] = $parsePriceValue($matches[1], $matches[2] ?? null);
            }
        }

        $yearPatterns = [
            '/(?:from|after|since|newer than|minimum)\s*((?:19|20)\d{2})/',
            '/(?:before|until|up to|older than|maximum)\s*((?:19|20)\d{2})/',
            '/((?:19|20)\d{2})\s*(?:to|-)\s*((?:19|20)\d{2})/',
            '/\b((?:19|20)\d{2})\b/',
        ];

        if (preg_match($yearPatterns[2], $lower, $matches)) {
            $result['yearMin'] = (int) $matches[1];
            $result['yearMax'] = (int) $matches[2];
        } else {
            if (preg_match($yearPatterns[0], $lower, $matches)) $result['yearMin'] = (int) $matches[1];
            if (preg_match($yearPatterns[1], $lower, $matches)) $result['yearMax'] = (int) $matches[1];
            if (!$result['yearMin'] && !$result['yearMax']) {
                if (preg_match($yearPatterns[3], $lower, $matches)) {
                    $year = (int) $matches[1];
                    if ($year >= 1990 && $year <= (int) date('Y') + 1) {
                        $result['yearMin'] = $year;
                        $result['yearMax'] = $year;
                    }
                }
            }
        }

        return $result;
    }

    private function buildAgentResponse($parsed, $resultCount)
    {
        $parts = [];

        if ($resultCount === 0) {
            $parts[] = "I couldn't find any vehicles matching your request.";
            if ($parsed['make']) $parts[] = "There are no {$parsed['make']} listings";
            if ($parsed['priceMax']) $parts[] = " under KSH " . number_format($parsed['priceMax']);
            if ($parsed['location']) $parts[] = " in {$parsed['location']}";
            $parts[] = ". Try broadening your search — perhaps a different price range or location.";
            return implode('', $parts);
        }

        $parts[] = "I found **{$resultCount}** vehicle" . ($resultCount !== 1 ? 's' : '');
        if ($parsed['make']) $parts[] = " from **" . ucfirst($parsed['make']) . "**";
        if ($parsed['model']) $parts[] = " " . ucfirst($parsed['model']);
        if ($parsed['category']) $parts[] = " in the **{$parsed['category']}** category";
        if ($parsed['location']) $parts[] = " located in **" . ucfirst($parsed['location']) . "**";
        if ($parsed['priceMin'] && $parsed['priceMax']) {
            $parts[] = " priced between **KSH " . number_format($parsed['priceMin']) . "** and **KSH " . number_format($parsed['priceMax']) . "**";
        } elseif ($parsed['priceMax']) {
            $parts[] = " under **KSH " . number_format($parsed['priceMax']) . "**";
        } elseif ($parsed['priceMin']) {
            $parts[] = " starting from **KSH " . number_format($parsed['priceMin']) . "**";
        }
        if ($parsed['fuelType']) $parts[] = " ({$parsed['fuelType']})";
        if ($parsed['transmission']) $parts[] = " with {$parsed['transmission']} transmission";
        $parts[] = ". Here are the top results:";

        return implode('', $parts);
    }

    private function buildSuggestions($parsed, $resultCount)
    {
        $suggestions = [];

        if ($resultCount === 0) {
            return ['Show all available vehicles', 'SUVs in Nairobi', 'Budget cars under 2M'];
        }

        if (!$parsed['priceMax'] && !$parsed['priceMin']) {
            $make = $parsed['make'] ?: 'Cars';
            $suggestions[] = "{$make} under 2M";
        }
        if (!$parsed['location']) {
            $make = $parsed['make'] ?: 'Cars';
            $suggestions[] = "{$make} in Nairobi";
        }
        if (!$parsed['fuelType']) {
            $cat = $parsed['category'] ?: 'SUVs';
            $suggestions[] = "Diesel {$cat}";
        }
        if ($parsed['make'] && !$parsed['model']) {
            $models = self::KNOWN_MODELS[$parsed['make']] ?? [];
            if (!empty($models)) {
                $suggestions[] = "{$parsed['make']} {$models[0]}";
            }
        }
        $suggestions[] = 'Compare with similar vehicles';

        return array_slice($suggestions, 0, 3);
    }

    public function agentChat(Request $request)
    {
        try {
            $message = $request->input('message');

            if (!$message || !is_string($message) || trim($message) === '') {
                return response()->json([
                    'error' => 'Please provide a message to search for vehicles.',
                ], 400);
            }

            $parsed = $this->parseNaturalLanguage($message);

            $query = Car::where('is_active', 'true')->where('status', 'active')->with('images');

            if ($parsed['make']) $query->where('make', 'ilike', '%' . $parsed['make'] . '%');
            if ($parsed['model']) $query->where('model', 'ilike', '%' . $parsed['model'] . '%');
            if ($parsed['location']) $query->where('view_location', 'ilike', '%' . $parsed['location'] . '%');
            if ($parsed['condition']) $query->where('car_condition', 'ilike', '%' . $parsed['condition'] . '%');
            if ($parsed['fuelType']) $query->where('fuel_type', 'ilike', '%' . $parsed['fuelType'] . '%');
            if ($parsed['transmission']) $query->where('transmission', 'ilike', '%' . $parsed['transmission'] . '%');
            if ($parsed['driveSystem']) $query->where('drive_system', 'ilike', '%' . $parsed['driveSystem'] . '%');
            if ($parsed['category']) $query->where('category', 'ilike', '%' . $parsed['category'] . '%');
            if ($parsed['priceMin']) $query->where('price', '>=', $parsed['priceMin']);
            if ($parsed['priceMax']) $query->where('price', '<=', $parsed['priceMax']);
            if ($parsed['yearMin']) $query->where('yom', '>=', $parsed['yearMin']);
            if ($parsed['yearMax']) $query->where('yom', '<=', $parsed['yearMax']);

            $cars = $query->orderByDesc('created_at')->limit(12)->get();

            $formattedCars = [];
            if ($cars->isNotEmpty()) {
                $formattedCars = $cars->map(function ($car) {
                    $data = $car->formatForApi();
                    $data['images'] = $car->images->map(function ($img) {
                        return ['image_url' => $img->image_url];
                    })->toArray();
                    return $data;
                })->toArray();
            }

            $agentMessage = $this->buildAgentResponse($parsed, $cars->count());
            $suggestions = $this->buildSuggestions($parsed, $cars->count());

            return response()->json([
                'message' => $agentMessage,
                'vehicles' => $formattedCars,
                'parsed' => [
                    'intent' => $parsed['intent'],
                    'make' => $parsed['make'],
                    'model' => $parsed['model'],
                    'location' => $parsed['location'],
                    'category' => $parsed['category'],
                    'priceRange' => [
                        'min' => $parsed['priceMin'],
                        'max' => $parsed['priceMax'],
                    ],
                    'yearRange' => [
                        'min' => $parsed['yearMin'],
                        'max' => $parsed['yearMax'],
                    ],
                    'fuelType' => $parsed['fuelType'],
                    'transmission' => $parsed['transmission'],
                    'driveSystem' => $parsed['driveSystem'],
                    'condition' => $parsed['condition'],
                ],
                'suggestions' => $suggestions,
                'totalResults' => $cars->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Agent chat error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong while searching. Please try again.',
            ], 500);
        }
    }

    public function agentGreeting()
    {
        $stats = $this->getAvailableStats();
        
        $make1 = $stats['makes'][0] ?? 'Toyota';
        $make2 = $stats['makes'][1] ?? 'Nissan';
        $cat1 = $stats['categories'][0] ?? 'SUV';
        
        return response()->json([
            'message' => "Hi! 👋 I'm **Kemo**, your Kemotives marketplace assistant. I can help you find the perfect vehicle. Try asking me something like:",
            'suggestions' => [
                "Find {$make1} {$cat1}s under 3M",
                "Show me {$make2} cars in Nairobi",
                'Affordable sedans with automatic transmission',
                'Latest arrivals this week',
            ],
        ]);
    }
}
