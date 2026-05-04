<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data        = json_decode(file_get_contents("php://input"), true);
$delivery_id = intval($data['delivery_id'] ?? 0);
$user_id     = intval($_SESSION['user_id']);

if ($delivery_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid delivery ID"]);
    exit;
}

// ─── Verify delivery belongs to calling supplier ──────────────────────────────
$check = $conn->prepare("
    SELECT d.delivery_id
    FROM deliveries d
    JOIN suppliers s ON d.supplier_id = s.supplier_id
    WHERE d.delivery_id = ? AND s.user_id = ?
");
$check->bind_param("ii", $delivery_id, $user_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden"]);
    exit;
}
$check->close();

// ─── Mark delivered ───────────────────────────────────────────────────────────
$stmt = $conn->prepare("UPDATE deliveries SET status = 'delivered' WHERE delivery_id = ?");
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$stmt->close();

// ─── Get linked facility ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT r.facility_id
    FROM deliveries d
    JOIN requests r ON d.request_id = r.request_id
    WHERE d.delivery_id = ?
");
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Delivery not found"]);
    exit;
}

$facility_id = intval($row['facility_id']);

// ─── Update stock ─────────────────────────────────────────────────────────────
$items_stmt = $conn->prepare("SELECT batch_id, quantity FROM delivery_items WHERE delivery_id = ?");
$items_stmt->bind_param("i", $delivery_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
$items_stmt->close();

$stock_stmt = $conn->prepare("
    INSERT INTO stock (facility_id, batch_id, quantity)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
");

while ($item = $items->fetch_assoc()) {
    $bid = intval($item['batch_id']);
    $qty = intval($item['quantity']);
    $stock_stmt->bind_param("iii", $facility_id, $bid, $qty);
    $stock_stmt->execute();
}
$stock_stmt->close();

echo json_encode(["status" => "success", "message" => "Delivery completed and stock updated"]);
?>
