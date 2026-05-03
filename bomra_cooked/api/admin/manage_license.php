<?php
// ─── License Management ───────────────────────────────────────────────────────
// Document: "The system issues licenses to pharmacies or suppliers that meet
// all requirements. It also allows licenses to be renewed when they expire.
// If a pharmacy or supplier fails to follow regulations the system can
// suspend their license."
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data   = json_decode(file_get_contents("php://input"), true);
$action = trim($data['action'] ?? '');   // issue | renew | suspend

$allowed_actions = ['issue', 'renew', 'suspend'];
if (!in_array($action, $allowed_actions, true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action. Must be: issue, renew, or suspend"]);
    exit;
}

// ─── ISSUE ────────────────────────────────────────────────────────────────────
if ($action === 'issue') {
    $holder_type = trim($data['holder_type'] ?? ''); // facility | supplier
    $holder_id   = intval($data['holder_id'] ?? 0);
    $expires_at  = trim($data['expires_at']  ?? '');

    if (!in_array($holder_type, ['facility', 'supplier'], true) || $holder_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid holder_type or holder_id"]);
        exit;
    }

    // Prevent duplicate active license
    $dup = $conn->prepare("
        SELECT license_id FROM licenses
        WHERE holder_type = ? AND holder_id = ? AND status = 'active'
    ");
    $dup->bind_param("si", $holder_type, $holder_id);
    $dup->execute();
    $dup->store_result();

    if ($dup->num_rows > 0) {
        $dup->close();
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "An active license already exists"]);
        exit;
    }
    $dup->close();

    $license_number = "LIC-" . strtoupper(bin2hex(random_bytes(4)));
    $issued_by      = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        INSERT INTO licenses (license_number, holder_type, holder_id, issued_by, expires_at, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param("ssiss", $license_number, $holder_type, $holder_id, $issued_by, $expires_at);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success", "license_number" => $license_number]);
    exit;
}

// ─── RENEW / SUSPEND ─────────────────────────────────────────────────────────
$license_id = intval($data['license_id'] ?? 0);
if ($license_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid license ID"]);
    exit;
}

if ($action === 'renew') {
    $expires_at = trim($data['expires_at'] ?? '');
    if (empty($expires_at)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "expires_at is required for renewal"]);
        exit;
    }
    $stmt = $conn->prepare("
        UPDATE licenses SET status = 'active', expires_at = ? WHERE license_id = ?
    ");
    $stmt->bind_param("si", $expires_at, $license_id);
} else {
    // suspend
    $stmt = $conn->prepare("
        UPDATE licenses SET status = 'suspended' WHERE license_id = ?
    ");
    $stmt->bind_param("i", $license_id);
}

$stmt->execute();
$stmt->close();

echo json_encode(["status" => "success", "message" => "License $action completed"]);
?>
