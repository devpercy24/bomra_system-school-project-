<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

$stmt = $conn->prepare("
    SELECT
        l.license_id,
        l.license_number,
        l.holder_type,
        l.holder_id,
        l.status,
        l.expires_at,
        l.issued_at,
        u.name AS issued_by_name
    FROM licenses l
    JOIN users u ON l.issued_by = u.user_id
    ORDER BY l.issued_at DESC
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
