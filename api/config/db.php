<?php
// ─── CORS headers (allow frontend origin in dev; restrict in production) ─────
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$conn = new mysqli("localhost", "root", "", "bomra_system");

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

$conn->set_charset("utf8mb4");
?>
