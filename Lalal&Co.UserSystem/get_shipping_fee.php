<?php
session_start();
require_once 'shippingFee.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_POST['city']) || empty($_POST['city'])) {
        throw new Exception('City is required');
    }
    
    $calculator = new JNTShippingCalculator();
    
    $city = $_POST['city'];
    $city = explode('|', $city)[0];
    $city = trim($city);
    
    if (empty($city)) {
        throw new Exception('Invalid city format');
    }
    
    $distance = $calculator->calculateDistance('South Caloocan', $city);
    
    if ($distance === null) {
        $fee = 75;
        $distance = 0;
        $tier = 'Unknown Location';
    } else {
        $fee = $calculator->calculateShippingFee('South Caloocan', $city);
        $tier = $calculator->getShippingTier($distance);
    }
    
    echo json_encode([
        'success' => true,
        'distance' => $distance,
        'fee' => $fee,
        'tier' => $tier,
        'city' => $city,
        'origin' => 'South Caloocan',
        'courier' => 'J&T Express'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'fee' => 75,
        'distance' => 0
    ]);
}
?>