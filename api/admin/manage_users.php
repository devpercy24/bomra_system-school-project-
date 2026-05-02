<?php
// ─── User Management ──────────────────────────────────────────────────────────
// Document: "The system allows new users to register and existing users to
// log in using their credentials. It also assigns roles to users which
// determine their permissions and access levels within the system."
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: list all users ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $conn->prepare("
        SELECT user_id, name, email, role, created_at FROM users ORDER BY created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $users]);
    exit;
}

// ─── POST: change a user's role ───────────────────────────────────────────────
if ($method === 'POST') {
    $data    = json_decode(file_get_contents("php://input"), true);
    $user_id = intval($data['user_id'] ?? 0);
    $role    = trim($data['role']      ?? '');

    $allowed_roles = ['supplier', 'facility', 'inspector'];
    if ($user_id <= 0 || !in_array($role, $allowed_roles, true)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid user_id or role"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->bind_param("si", $role, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success", "message" => "Role updated"]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
?>
