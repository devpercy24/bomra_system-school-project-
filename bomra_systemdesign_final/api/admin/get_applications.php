<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

// ─── List all medicine registration applications with batch + submitter info ──
$stmt = $conn->prepare("
    SELECT
        a.application_id,
        a.batch_id,
        a.status,
        a.submitted_by,
        a.review_notes,
        DATE_FORMAT(a.review_date, '%Y-%m-%d %H:%i') AS review_date,
        DATE_FORMAT(a.created_at,  '%Y-%m-%d %H:%i') AS submitted_at,
        u.name            AS submitter_name,
        mb.batch_number,
        mb.quantity,
        mb.expiry_date,
        m.name            AS medicine_name,
        m.manufacturer,
        s.name            AS supplier_name
    FROM applications a
    JOIN users u             ON a.submitted_by  = u.user_id
    JOIN medicine_batches mb ON a.batch_id       = mb.batch_id
    JOIN medicines m         ON mb.medicine_id   = m.medicine_id
    JOIN suppliers s         ON mb.supplier_id   = s.supplier_id
    ORDER BY a.application_id DESC
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
