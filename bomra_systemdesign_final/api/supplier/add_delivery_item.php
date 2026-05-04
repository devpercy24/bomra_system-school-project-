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
$batch_id    = intval($data['batch_id']    ?? 0);
$quantity    = intval($data['quantity']    ?? 0);
$user_id     = intval($_SESSION['user_id']);

if ($delivery_id <= 0 || $batch_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
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

$stmt = $conn->prepare("
    INSERT INTO delivery_items (delivery_id, batch_id, quantity)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iii", $delivery_id, $batch_id, $quantity);
$stmt->execute();
$stmt->close();

echo json_encode(["status" => "success"]);
?>
