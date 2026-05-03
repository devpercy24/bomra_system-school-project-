<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data     = json_decode(file_get_contents("php://input"), true);
$batch_id = intval($data['batch_id'] ?? 0);
$user_id  = intval($_SESSION['user_id']);

if ($batch_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid batch ID"]);
    exit;
}

// ─── Verify this batch belongs to the calling supplier ────────────────────────
$check = $conn->prepare("
    SELECT mb.batch_id
    FROM medicine_batches mb
    JOIN suppliers s ON mb.supplier_id = s.supplier_id
    WHERE mb.batch_id = ? AND s.user_id = ?
");
$check->bind_param("ii", $batch_id, $user_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden"]);
    exit;
}
$check->close();

// ─── Prevent duplicate pending application for same batch ────────────────────
$dup = $conn->prepare("
    SELECT application_id FROM applications
    WHERE batch_id = ? AND status = 'pending'
");
$dup->bind_param("i", $batch_id);
$dup->execute();
$dup->store_result();

if ($dup->num_rows > 0) {
    $dup->close();
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "A pending application already exists for this batch"]);
    exit;
}
$dup->close();

$stmt = $conn->prepare("
    INSERT INTO applications (batch_id, submitted_by, status)
    VALUES (?, ?, 'pending')
");
$stmt->bind_param("ii", $batch_id, $user_id);
$stmt->execute();
$app_id = $stmt->insert_id;
$stmt->close();

echo json_encode([
    "status"         => "success",
    "application_id" => $app_id,
    "message"        => "Application submitted successfully"
]);
?>
