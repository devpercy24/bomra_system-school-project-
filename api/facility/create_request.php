<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('facility');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// ─── Get facility ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT facility_id FROM facilities WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$f = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$f) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Facility not found for this user"]);
    exit;
}

$facility_id = intval($f['facility_id']);

$stmt = $conn->prepare("
    INSERT INTO requests (facility_id, requested_by)
    VALUES (?, ?)
");
$stmt->bind_param("ii", $facility_id, $user_id);
$stmt->execute();
$request_id = $stmt->insert_id;
$stmt->close();

echo json_encode(["status" => "success", "request_id" => $request_id]);
?>
