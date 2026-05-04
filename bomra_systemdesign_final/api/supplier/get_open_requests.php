<?php
// ─── Get Open Supply Requests (for Suppliers) ─────────────────────────────────
// Returns requests that have no completed delivery yet.
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

$stmt = $conn->prepare("
    SELECT
        r.request_id,
        r.created_at,
        f.name            AS facility_name,
        f.address         AS facility_location,
        u.name            AS requested_by
    FROM requests r
    JOIN facilities f  ON r.facility_id   = f.facility_id
    JOIN users u       ON r.requested_by  = u.user_id
    WHERE r.request_id NOT IN (
        SELECT request_id FROM deliveries WHERE status = 'delivered'
    )
    ORDER BY r.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["status" => "success", "data" => $data]);
?>
