<?php
require_once("../config/db.php");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data         = json_decode(file_get_contents("php://input"), true);
$user_id      = intval($data['user_id']    ?? 0);
$temp_code    = trim($data['temp_code']    ?? '');
$new_password = trim($data['new_password'] ?? '');

if (!$user_id || empty($temp_code) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Password strength validation
if (!preg_match('/^[\x20-\x7E]+$/', $new_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Password must contain only standard characters"]);
    exit;
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\\\|`~]{8,}$/', $new_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters with uppercase, lowercase and a number"]);
    exit;
}

// Validate the temp code against the stored token
$tok = $conn->prepare(
    "SELECT token_id, token_hash FROM password_reset_tokens
     WHERE user_id = ? AND expires_at > NOW()
     ORDER BY token_id DESC LIMIT 1"
);
$tok->bind_param("i", $user_id);
$tok->execute();
$tok_row = $tok->get_result()->fetch_assoc();
$tok->close();

if (!$tok_row || !password_verify($temp_code, $tok_row['token_hash'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid or expired code. Please request a new one."]);
    exit;
}

// Update the password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);
$upd    = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$upd->bind_param("si", $hashed, $user_id);

if (!$upd->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Could not update password"]);
    exit;
}
$upd->close();

// Delete the token — cannot be reused
$del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
$del->bind_param("i", $user_id);
$del->execute();
$del->close();

echo json_encode(["status" => "success", "message" => "Password updated successfully"]);
?>
