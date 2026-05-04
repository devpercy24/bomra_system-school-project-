<?php
// ─── Pharmacy Inspection ──────────────────────────────────────────────────────
// Document: "The system schedules inspections by assigning dates and inspectors.
// During the inspection the inspector records findings directly into the system.
// The system then evaluates the results to determine whether the pharmacy
// meets the required standards."
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('inspector');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data        = json_decode(file_get_contents("php://input"), true);
$facility_id = intval($data['facility_id']    ?? 0);
$status      = trim($data['status']           ?? '');
$notes       = trim($data['notes']            ?? '');
$scheduled   = trim($data['scheduled_date']   ?? '');   // NEW: schedule date
$user_id     = intval($_SESSION['user_id']);

if ($facility_id <= 0 || empty($status)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "facility_id and status are required"]);
    exit;
}

// ─── Whitelist allowed statuses ───────────────────────────────────────────────
$allowed = ['passed', 'failed', 'scheduled'];
if (!in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid status. Must be: passed, failed, or scheduled"]);
    exit;
}

// ─── Validate scheduled_date when status = 'scheduled' ───────────────────────
if ($status === 'scheduled' && empty($scheduled)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "scheduled_date is required when status is 'scheduled'"]);
    exit;
}

$scheduled_val = !empty($scheduled) ? $scheduled : null;

$stmt = $conn->prepare("
    INSERT INTO inspections (facility_id, inspector_id, status, notes, scheduled_date)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iisss", $facility_id, $user_id, $status, $notes, $scheduled_val);
$stmt->execute();
$inspection_id = $stmt->insert_id;
$stmt->close();

echo json_encode(["status" => "success", "inspection_id" => $inspection_id]);
?>
