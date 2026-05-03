<?php
// ─── Drug Reaction Reports ────────────────────────────────────────────────────
// Document: "The system captures reports about adverse drug reactions and
// validates the information provided. It then analyzes the data to identify
// patterns or risks. After analysis the report is stored for future reference
// and monitoring."
require_once("../middleware/auth.php");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

// ─── POST: submit a drug reaction report ─────────────────────────────────────
if ($method === 'POST') {
    $data       = json_decode(file_get_contents("php://input"), true);
    $batch_id   = intval($data['batch_id']    ?? 0);
    $reaction   = trim($data['reaction']      ?? '');
    $severity   = trim($data['severity']      ?? '');
    $notes      = trim($data['notes']         ?? '');
    $user_id    = intval($_SESSION['user_id']);

    if ($batch_id <= 0 || empty($reaction) || empty($severity)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "batch_id, reaction and severity are required"]);
        exit;
    }

    // ─── Whitelist severity levels ────────────────────────────────────────────
    $allowed_severities = ['mild', 'moderate', 'severe'];
    if (!in_array($severity, $allowed_severities, true)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid severity. Must be: mild, moderate, or severe"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO drug_reactions (batch_id, reported_by, reaction, severity, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisss", $batch_id, $user_id, $reaction, $severity, $notes);
    $stmt->execute();
    $report_id = $stmt->insert_id;
    $stmt->close();

    echo json_encode(["status" => "success", "report_id" => $report_id]);
    exit;
}

// ─── GET: list all drug reaction reports (admin & inspector) ─────────────────
if ($method === 'GET') {
    $role = $_SESSION['role'];
    if (!in_array($role, ['admin', 'inspector'], true)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Forbidden"]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            dr.report_id,
            dr.reaction,
            dr.severity,
            dr.notes,
            dr.reported_at,
            mb.batch_number,
            mb.expiry_date,
            m.name         AS medicine_name,
            m.manufacturer,
            u.name         AS reported_by_name
        FROM drug_reactions dr
        JOIN medicine_batches mb ON dr.batch_id   = mb.batch_id
        JOIN medicines m         ON mb.medicine_id = m.medicine_id
        JOIN users u             ON dr.reported_by = u.user_id
        ORDER BY dr.reported_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
?>
