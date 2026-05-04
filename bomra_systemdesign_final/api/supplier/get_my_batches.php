<?php
// ─── Get My Batches (with application status) ────────────────────────────────
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("
    SELECT
        mb.batch_id,
        mb.batch_number,
        mb.quantity,
        mb.expiry_date,
        mb.created_at,
        m.name         AS medicine_name,
        m.manufacturer,
        a.application_id,
        a.status       AS application_status
    FROM medicine_batches mb
    JOIN medicines m ON mb.medicine_id = m.medicine_id
    JOIN suppliers s ON mb.supplier_id = s.supplier_id
    LEFT JOIN applications a ON a.batch_id = mb.batch_id
    WHERE s.user_id = ?
    ORDER BY mb.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    $batches[] = $row;
}
$stmt->close();

echo json_encode(["status" => "success", "data" => $batches]);
?>
