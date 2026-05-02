<?php
require_once("../config/db.php");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$name     = trim($data['name']     ?? '');
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');
$role     = trim($data['role']     ?? '');

// ─── Validate required fields ─────────────────────────────────────────────────
if (empty($name) || empty($email) || empty($password) || empty($role)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// ─── Block admin self-registration ───────────────────────────────────────────
$allowed_roles = ['supplier', 'facility', 'inspector'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid role"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit;
}

// ─── Password strength + no emojis / non-ASCII ───────────────────────────────
// Allow only printable ASCII (0x20-0x7E). Rejects emojis and Unicode outside that range.
if (!preg_match('/^[\x20-\x7E]+$/', $password)) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Password must contain only standard characters (no emojis or special Unicode)"
    ]);
    exit;
}
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\\\|`~]{8,}$/', $password)) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Password must be at least 8 characters with uppercase, lowercase, and a number"
    ]);
    exit;
}

// ─── Duplicate email check ────────────────────────────────────────────────────
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}
$check->close();

// ─── Insert user ──────────────────────────────────────────────────────────────
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hashed, $role);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Registration failed"]);
    exit;
}

$user_id = $stmt->insert_id;
$stmt->close();

// ─── Auto-create role profile row ─────────────────────────────────────────────
if ($role === 'supplier') {
    $p = $conn->prepare("INSERT INTO suppliers (user_id, name) VALUES (?, ?)");
    $p->bind_param("is", $user_id, $name);
    $p->execute();
    $p->close();
} elseif ($role === 'facility') {
    $p = $conn->prepare("INSERT INTO facilities (user_id, name) VALUES (?, ?)");
    $p->bind_param("is", $user_id, $name);
    $p->execute();
    $p->close();
}
// Inspectors just use the users table; no separate profile needed.

http_response_code(201);
echo json_encode(["status" => "success", "message" => "Account created"]);
?>
