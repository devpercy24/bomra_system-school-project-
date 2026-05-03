<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data       = json_decode(file_get_contents("php://input"), true);
$request_id = intval($data['request_id'] ?? 0);
$user_id    = intval($_SESSION['user_id']);

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request ID"]);
    exit;
}

// ─── Get supplier_id ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$s) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Supplier not found"]);
    exit;
}

$supplier_id = intval($s['supplier_id']);

$stmt = $conn->prepare("
    INSERT INTO deliveries (request_id, supplier_id, status)
    VALUES (?, ?, 'shipped')
");
$stmt->bind_param("ii", $request_id, $supplier_id);
$stmt->execute();
$delivery_id = $stmt->insert_id;
$stmt->close();

echo json_encode(["status" => "success", "delivery_id" => $delivery_id]);
?>
