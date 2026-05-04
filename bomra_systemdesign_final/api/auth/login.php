<?php
require_once("../config/db.php");

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and password required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

$force_change = false;

if (!$user) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}

$password_ok = password_verify($password, $user['password']);

if (!$password_ok) {
    // Check if this matches an active temporary reset code
    $tok = $conn->prepare(
        "SELECT token_id, token_hash FROM password_reset_tokens
         WHERE user_id = ? AND expires_at > NOW()
         ORDER BY token_id DESC LIMIT 1"
    );
    $tok->bind_param("i", $user['user_id']);
    $tok->execute();
    $tok_row = $tok->get_result()->fetch_assoc();
    $tok->close();

    if ($tok_row && password_verify($password, $tok_row['token_hash'])) {
        // Valid temp code — allow login but mark forced change required
        $force_change = true;
        // Leave the token in place; change_password.php will delete it
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
        exit;
    }
}

// ─── Admin whitelist ──────────────────────────────────────────────────────────
if ($user['role'] === 'admin') {
    $hardcoded_admins = ['percy', 'yoliswa', 'patso', 'mphoyame'];
    if (!in_array(strtolower(trim($user['name'])), $hardcoded_admins, true)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Admin account not authorised"]);
        exit;
    }
}

// ─── Regenerate session ID (prevents session fixation) ───────────────────────
session_regenerate_id(true);

$_SESSION['user_id']       = $user['user_id'];
$_SESSION['role']          = $user['role'];
$_SESSION['username']      = strtolower(trim($user['name']));
$_SESSION['last_activity'] = time();
$_SESSION['force_change']  = $force_change;

echo json_encode([
    "status" => "success",
    "data"   => [
        "user_id"      => $user['user_id'],
        "name"         => $user['name'],
        "email"        => $user['email'],
        "role"         => $user['role'],
        "force_change" => $force_change
    ]
]);
?>
