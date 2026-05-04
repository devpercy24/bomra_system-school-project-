<?php
// ─── Get Facility Supply Requests with Delivery Status ───────────────────────
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('facility');

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT facility_id FROM facilities WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$f = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$f) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Facility not found"]);
    exit;
}

$facility_id = intval($f['facility_id']);

$stmt = $conn->prepare("
    SELECT
        r.request_id,
        r.created_at,
        COALESCE(d.status, 'pending') AS delivery_status,
        d.delivery_id,
        s.name AS supplier_name,
        (SELECT COUNT(*) FROM delivery_items di WHERE di.delivery_id = d.delivery_id) AS item_count
    FROM requests r
    LEFT JOIN deliveries d ON r.request_id = d.request_id
    LEFT JOIN suppliers s  ON d.supplier_id = s.supplier_id
    WHERE r.facility_id = ?
    ORDER BY r.request_id DESC
    LIMIT 20
");
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["status" => "success", "data" => $data]);
?>
