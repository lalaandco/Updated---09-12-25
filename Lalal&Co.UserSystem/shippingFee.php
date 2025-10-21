<?php
/**
 * J&T Express Distance-Based Shipping Fee Calculator
 * Philippines Courier Service
 * 
 * Base rates structure:
 * - Metro Manila (0-30 km): ₱50-75
 * - CALABARZON (30-60 km): ₱75-100
 * - Provincial (60-120 km): ₱100-150
 * - Far Provincial (120+ km): ₱150-250+
 * 
 * Can be adjusted based on actual J&T rates
 */

class JNTShippingCalculator {
    
    // City coordinates (latitude, longitude) - South Caloocan as reference
    private $cityCoordinates = [
        // Client Location (Origin)
        'South Caloocan' => ['lat' => 14.6200, 'lng' => 120.9700],
        
        // Metro Manila
        'Manila' => ['lat' => 14.5994, 'lng' => 120.9842],
        'Quezon City' => ['lat' => 14.6349, 'lng' => 121.0388],
        'Caloocan' => ['lat' => 14.6423, 'lng' => 120.9832],
        'Las Piñas' => ['lat' => 14.3534, 'lng' => 120.9200],
        'Makati' => ['lat' => 14.5546, 'lng' => 121.0175],
        'Malabon' => ['lat' => 14.6652, 'lng' => 120.9625],
        'Mandaluyong' => ['lat' => 14.5758, 'lng' => 121.0413],
        'Marikina' => ['lat' => 14.6427, 'lng' => 121.1047],
        'Muntinlupa' => ['lat' => 14.3775, 'lng' => 121.0447],
        'Navotas' => ['lat' => 14.6478, 'lng' => 120.8270],
        'Parañaque' => ['lat' => 14.3506, 'lng' => 121.0300],
        'Pasay' => ['lat' => 14.5485, 'lng' => 121.0001],
        'Pasig' => ['lat' => 14.5734, 'lng' => 121.5735],
        'Pateros' => ['lat' => 14.5626, 'lng' => 121.0919],
        'San Juan' => ['lat' => 14.6063, 'lng' => 121.0658],
        'Taguig' => ['lat' => 14.5245, 'lng' => 121.0347],
        'Valenzuela' => ['lat' => 14.6959, 'lng' => 120.9697],
        
        // CALABARZON (nearby provinces)
        'Antipolo' => ['lat' => 14.5894, 'lng' => 121.1758],
        'Bacoor' => ['lat' => 14.4189, 'lng' => 120.7911],
        'Dasmariñas' => ['lat' => 14.2975, 'lng' => 120.8731],
        'Imus' => ['lat' => 14.3063, 'lng' => 120.8289],
        'Biñan' => ['lat' => 14.3167, 'lng' => 121.0558],
        'Calamba' => ['lat' => 14.2081, 'lng' => 121.1689],
        'San Pedro' => ['lat' => 14.3583, 'lng' => 121.0167],
        'Santa Rosa' => ['lat' => 14.2842, 'lng' => 121.1897],
        
        // Other major cities (sample)
        'Baguio' => ['lat' => 16.4023, 'lng' => 120.5960],
        'Cebu City' => ['lat' => 10.3157, 'lng' => 123.8854],
        'Davao City' => ['lat' => 7.0731, 'lng' => 125.6130],
        'Cagayan de Oro' => ['lat' => 8.4866, 'lng' => 124.6492],
        'Iloilo City' => ['lat' => 10.6898, 'lng' => 122.5673],
        'Bacolod' => ['lat' => 10.3906, 'lng' => 123.0352],
    ];
    
    // Reference point: South Caloocan (Client Location)
    private $referenceCity = 'South Caloocan';
    
    /**
     * Calculate distance between two cities using Haversine formula
     * Returns distance in kilometers
     */
    public function calculateDistance($fromCity, $toCity) {
        // Normalize city names
        $fromCity = $this->normalizeCityName($fromCity);
        $toCity = $this->normalizeCityName($toCity);
        
        if (!isset($this->cityCoordinates[$fromCity]) || !isset($this->cityCoordinates[$toCity])) {
            // Default to base rate if city not found
            return null;
        }
        
        $from = $this->cityCoordinates[$fromCity];
        $to = $this->cityCoordinates[$toCity];
        
        // Haversine formula
        $latFrom = deg2rad($from['lat']);
        $lonFrom = deg2rad($from['lng']);
        $latTo = deg2rad($to['lat']);
        $lonTo = deg2rad($to['lng']);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * asin(sqrt($a));
        
        // Earth's radius in kilometers
        $radius = 6371;
        
        return round($c * $radius, 2);
    }
    
    /**
     * Calculate shipping fee based on distance
     * Using tiered pricing system similar to J&T Express
     */
    public function calculateShippingFee($fromCity, $toCity, $weight = 1) {
        $distance = $this->calculateDistance($fromCity, $toCity);
        
        // If city not found, use default fee
        if ($distance === null) {
            return 75; // Default base rate
        }
        
        // Base rate structure (in Philippine Pesos)
        // Adjusted for 1kg; multiply by weight if needed
        
        if ($distance == 0) {
            // Same city - minimal fee
            return 25;
        } elseif ($distance <= 30) {
            // Metro Manila area (0-30km)
            // Base: ₱50-75
            $fee = 50 + ($distance * 0.75);
            return round($fee);
        } elseif ($distance <= 60) {
            // CALABARZON area (30-60km)
            // Base: ₱75-100
            $fee = 75 + (($distance - 30) * 1.00);
            return round($fee);
        } elseif ($distance <= 120) {
            // Provincial area (60-120km)
            // Base: ₱100-150
            $fee = 100 + (($distance - 60) * 0.90);
            return round($fee);
        } elseif ($distance <= 250) {
            // Far provincial (120-250km)
            // Base: ₱150-250
            $fee = 150 + (($distance - 120) * 0.60);
            return round($fee);
        } else {
            // Very far provincial (250km+)
            // Base: ₱250+
            $fee = 250 + (($distance - 250) * 0.40);
            return round($fee);
        }
    }
    
    /**
     * Get shipping tier based on distance
     */
    public function getShippingTier($distance) {
        if ($distance == 0) {
            return 'Same City';
        } elseif ($distance <= 30) {
            return 'Metro Manila';
        } elseif ($distance <= 60) {
            return 'CALABARZON';
        } elseif ($distance <= 120) {
            return 'Provincial';
        } elseif ($distance <= 250) {
            return 'Far Provincial';
        } else {
            return 'Very Far Provincial';
        }
    }
    
    /**
     * Normalize city name for lookup
     */
    private function normalizeCityName($city) {
        $city = trim($city);
        $city = ucwords(strtolower($city));
        
        // Handle special cases
        $replacements = [
            'Quezon City' => 'Quezon City',
            'Las Pinas' => 'Las Piñas',
            'Dasmarinas' => 'Dasmariñas',
            'Binan' => 'Biñan',
            'Paranaque' => 'Parañaque',
            'Baguio City' => 'Baguio',
            'Cebu' => 'Cebu City',
            'Davao' => 'Davao City',
        ];
        
        foreach ($replacements as $key => $value) {
            if (stripos($city, $key) !== false) {
                return $value;
            }
        }
        
        return $city;
    }
    
    /**
     * Get available cities
     */
    public function getAvailableCities() {
        return array_keys($this->cityCoordinates);
    }
}

?>