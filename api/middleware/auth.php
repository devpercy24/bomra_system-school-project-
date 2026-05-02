<?php
// ─── Session hardening ────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ─── Session timeout: 30 minutes ─────────────────────────────────────────────
$timeout = 1800;

if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $timeout) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Session expired"]);
        exit;
    }
}

$_SESSION['last_activity'] = time();

// ─── Require authenticated session ───────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

// ─── Helper: enforce a specific role ─────────────────────────────────────────
function require_role(string $role): void {
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Forbidden"]);
        exit;
    }
}

// ─── Helper: enforce admin with hardcoded whitelist ──────────────────────────
function require_admin(): void {
    require_role('admin');

    $hardcoded_admins = ['percy', 'yoliswa', 'patso', 'mphoyame'];
    $name = strtolower(trim($_SESSION['username'] ?? ''));

    if (!in_array($name, $hardcoded_admins, true)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Admin account not authorised"]);
        exit;
    }
}
?>
