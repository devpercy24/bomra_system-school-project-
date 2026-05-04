<?php
// ─── Facility Stock View ──────────────────────────────────────────────────────
// Document: "It is difficult to monitor stock, detect expired medicines and
// identify counterfeit drugs."
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

// ─── Fetch stock with medicine, batch and expiry info ─────────────────────────
$stmt = $conn->prepare("
    SELECT
        st.stock_id,
        st.quantity,
        mb.batch_number,
        mb.expiry_date,
        m.name            AS medicine_name,
        m.manufacturer,
        s.name            AS supplier_name,
        CASE
            WHEN mb.expiry_date < CURDATE() THEN 'expired'
            WHEN mb.expiry_date < DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'expiring_soon'
            ELSE 'ok'
        END AS expiry_status
    FROM stock st
    JOIN medicine_batches mb ON st.batch_id    = mb.batch_id
    JOIN medicines m         ON mb.medicine_id = m.medicine_id
    JOIN suppliers s         ON mb.supplier_id = s.supplier_id
    WHERE st.facility_id = ?
    ORDER BY mb.expiry_date ASC
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
