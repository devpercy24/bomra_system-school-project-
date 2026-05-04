<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$app_id  = intval($data['application_id'] ?? 0);
$status  = trim($data['status']           ?? '');
$notes   = trim($data['notes']            ?? '');   // NEW: admin review notes
$user_id = $_SESSION['user_id'];

if ($app_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid application ID"]);
    exit;
}

$allowed_statuses = ['approved', 'rejected'];
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid status"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE applications
    SET status = ?, reviewed_by = ?, review_date = NOW(), review_notes = ?
    WHERE application_id = ?
");
$stmt->bind_param("sisi", $status, $user_id, $notes, $app_id);
$stmt->execute();
$stmt->close();

// ─── Sync approval_status on medicine_batches ─────────────────────────────────
// (Redundant if the DB trigger exists, but safe to run either way)
$sync = $conn->prepare("
    UPDATE medicine_batches mb
    JOIN applications a ON a.batch_id = mb.batch_id
    SET mb.approval_status = ?
    WHERE a.application_id = ?
");
$sync->bind_param("si", $status, $app_id);
$sync->execute();
$sync->close();

echo json_encode(["status" => "success", "message" => "Application $status"]);
?>
