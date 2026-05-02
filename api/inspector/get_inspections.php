<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('inspector');

$user_id = intval($_SESSION['user_id']);

// ─── Inspectors see their own inspections; admins see all ────────────────────
$stmt = $conn->prepare("
    SELECT
        i.inspection_id,
        i.status,
        i.notes,
        i.scheduled_date,
        i.inspected_at,
        f.name AS facility_name,
        u.name AS inspector_name
    FROM inspections i
    JOIN facilities f ON i.facility_id = f.facility_id
    JOIN users u      ON i.inspector_id = u.user_id
    WHERE i.inspector_id = ?
    ORDER BY i.inspected_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["status" => "success", "data" => $data]);
?>
