<?php
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data    = json_decode(file_get_contents("php://input"), true);
$batch_id = intval($data['batch_id'] ?? 0);
$user_id  = $_SESSION['user_id'];

if ($batch_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid batch ID"]);
    exit;
}

// ─── Only issue cert for approved applications ────────────────────────────────
$check = $conn->prepare("
    SELECT application_id FROM applications
    WHERE batch_id = ? AND status = 'approved'
");
$check->bind_param("i", $batch_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Application not approved yet"]);
    exit;
}
$check->close();

// ─── Prevent duplicate certificates ──────────────────────────────────────────
$dup = $conn->prepare("SELECT certificate_id FROM certificates WHERE batch_id = ?");
$dup->bind_param("i", $batch_id);
$dup->execute();
$dup->store_result();

if ($dup->num_rows > 0) {
    $dup->close();
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Certificate already issued for this batch"]);
    exit;
}
$dup->close();

// ─── Cryptographically secure certificate number ──────────────────────────────
$cert_number = "CERT-" . strtoupper(bin2hex(random_bytes(4)));

$stmt = $conn->prepare("
    INSERT INTO certificates (batch_id, issued_by, certificate_number)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iis", $batch_id, $user_id, $cert_number);
$stmt->execute();
$stmt->close();

echo json_encode(["status" => "success", "certificate_number" => $cert_number]);
?>
