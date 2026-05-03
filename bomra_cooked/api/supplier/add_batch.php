<?php
// ─── Add Medicine Batch ───────────────────────────────────────────────────────
// Document: "The system captures medicine details such as name, manufacturer,
// batch number and expiry date."
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_role('supplier');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$medicine_name  = trim($data['medicine_name']  ?? '');
$manufacturer   = trim($data['manufacturer']   ?? '');
$batch_number   = trim($data['batch_number']   ?? '');
$expiry_date    = trim($data['expiry_date']     ?? '');
$quantity       = intval($data['quantity']      ?? 0);
$user_id        = intval($_SESSION['user_id']);

if (empty($medicine_name) || empty($manufacturer) || empty($batch_number) || empty($expiry_date) || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// ─── Validate expiry date format ──────────────────────────────────────────────
$expiry = DateTime::createFromFormat('Y-m-d', $expiry_date);
if (!$expiry || $expiry->format('Y-m-d') !== $expiry_date) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid expiry_date format. Use YYYY-MM-DD"]);
    exit;
}

if ($expiry <= new DateTime()) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Expiry date must be in the future"]);
    exit;
}

// ─── Get or create medicine record ───────────────────────────────────────────
$m = $conn->prepare("SELECT medicine_id FROM medicines WHERE name = ? AND manufacturer = ?");
$m->bind_param("ss", $medicine_name, $manufacturer);
$m->execute();
$mres = $m->get_result()->fetch_assoc();
$m->close();

if ($mres) {
    $medicine_id = intval($mres['medicine_id']);
} else {
    $ins = $conn->prepare("INSERT INTO medicines (name, manufacturer) VALUES (?, ?)");
    $ins->bind_param("ss", $medicine_name, $manufacturer);
    $ins->execute();
    $medicine_id = $ins->insert_id;
    $ins->close();
}

// ─── Get supplier_id ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row  = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Supplier not found"]);
    exit;
}

$supplier_id = intval($row['supplier_id']);

// ─── Insert batch ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO medicine_batches (medicine_id, supplier_id, batch_number, quantity, expiry_date)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("iisis", $medicine_id, $supplier_id, $batch_number, $quantity, $expiry_date);
$stmt->execute();
$batch_id = $stmt->insert_id;
$stmt->close();

echo json_encode(["status" => "success", "batch_id" => $batch_id]);
?>
