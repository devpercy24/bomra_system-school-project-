<?php
// ─── Get Supplier Deliveries ──────────────────────────────────────────────────
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

$user_id = intval($_SESSION['user_id']);

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
    SELECT
        d.delivery_id,
        d.status,
        d.created_at,
        r.request_id,
        f.name AS facility_name,
        (SELECT COUNT(*) FROM delivery_items di WHERE di.delivery_id = d.delivery_id) AS item_count
    FROM deliveries d
    JOIN requests r    ON d.request_id  = r.request_id
    JOIN facilities f  ON r.facility_id = f.facility_id
    WHERE d.supplier_id = ?
    ORDER BY d.delivery_id DESC
    LIMIT 20
");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["status" => "success", "data" => $data]);
?>
