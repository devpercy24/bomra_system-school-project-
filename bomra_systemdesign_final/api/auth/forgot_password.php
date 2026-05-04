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

$data  = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid email required"]);
    exit;
}

// Check user exists and is not an admin
$stmt = $conn->prepare("SELECT user_id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    // Return error — email not found
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "No account found for that email address"]);
    exit;
}

if ($user['role'] === 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Admin accounts cannot use self-service password reset. Contact BoMRA IT support."]);
    exit;
}

// Generate a random 6-digit code
$plain_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$hashed     = password_hash($plain_code, PASSWORD_DEFAULT);
$expires_at = date('Y-m-d H:i:s', time() + 900); // 15 minutes

// Delete any existing reset token for this user
$del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
$del->bind_param("i", $user['user_id']);
$del->execute();
$del->close();

// Insert new token
$ins = $conn->prepare(
    "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
);
$ins->bind_param("iss", $user['user_id'], $hashed, $expires_at);

if (!$ins->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Could not generate reset code"]);
    exit;
}
$ins->close();

// Return the plain code to display on screen
// (In production this would be emailed; since there is no mail server the code is shown directly)
echo json_encode([
    "status" => "success",
    "code"   => $plain_code
]);
?>
