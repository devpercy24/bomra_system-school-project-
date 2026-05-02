<?php
// ─── Reports and Statistics ───────────────────────────────────────────────────
// Document: "The system generates reports using stored data. Users can view
// these reports within the system. Reports can also be exported for further
// use such as sharing or printing."
require_once("../middleware/auth.php");
require_once("../config/db.php");

require_admin();

$type = trim($_GET['type'] ?? 'summary');

switch ($type) {

    // ─── Summary dashboard ────────────────────────────────────────────────────
    case 'summary':
        $stats = [];

        $queries = [
            'total_users'        => "SELECT COUNT(*) AS c FROM users",
            'total_suppliers'    => "SELECT COUNT(*) AS c FROM suppliers",
            'total_facilities'   => "SELECT COUNT(*) AS c FROM facilities",
            'pending_apps'       => "SELECT COUNT(*) AS c FROM applications WHERE status = 'pending'",
            'approved_apps'      => "SELECT COUNT(*) AS c FROM applications WHERE status = 'approved'",
            'rejected_apps'      => "SELECT COUNT(*) AS c FROM applications WHERE status = 'rejected'",
            'total_inspections'  => "SELECT COUNT(*) AS c FROM inspections",
            'failed_inspections' => "SELECT COUNT(*) AS c FROM inspections WHERE status = 'failed'",
            'active_licenses'    => "SELECT COUNT(*) AS c FROM licenses WHERE status = 'active'",
            'drug_reactions'     => "SELECT COUNT(*) AS c FROM drug_reactions",
        ];

        foreach ($queries as $key => $sql) {
            $r = $conn->query($sql);
            $stats[$key] = intval($r->fetch_assoc()['c']);
        }

        // ─── Expired medicines in stock ───────────────────────────────────────
        $exp = $conn->query("
            SELECT COUNT(*) AS c
            FROM stock st
            JOIN medicine_batches mb ON st.batch_id = mb.batch_id
            WHERE mb.expiry_date < CURDATE() AND st.quantity > 0
        ");
        $stats['expired_stock_batches'] = intval($exp->fetch_assoc()['c']);

        echo json_encode(["status" => "success", "data" => $stats]);
        break;

    // ─── Inspections report ───────────────────────────────────────────────────
    case 'inspections':
        $stmt = $conn->prepare("
            SELECT
                i.inspection_id,
                i.status,
                i.notes,
                i.scheduled_date,
                i.inspected_at,
                f.name AS facility_name,
                u.name AS inspector_name
            FROM inspections i
            JOIN facilities f ON i.facility_id  = f.facility_id
            JOIN users u      ON i.inspector_id = u.user_id
            ORDER BY i.inspected_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["status" => "success", "data" => $rows]);
        break;

    // ─── Expired / expiring medicines ─────────────────────────────────────────
    case 'expired_medicines':
        $stmt = $conn->prepare("
            SELECT
                mb.batch_number,
                mb.expiry_date,
                mb.quantity         AS batch_qty,
                m.name              AS medicine_name,
                m.manufacturer,
                s.name              AS supplier_name,
                COALESCE(SUM(st.quantity), 0) AS stock_qty
            FROM medicine_batches mb
            JOIN medicines m  ON mb.medicine_id = m.medicine_id
            JOIN suppliers s  ON mb.supplier_id = s.supplier_id
            LEFT JOIN stock st ON mb.batch_id   = st.batch_id
            WHERE mb.expiry_date < CURDATE()
            GROUP BY mb.batch_id
            ORDER BY mb.expiry_date ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["status" => "success", "data" => $rows]);
        break;

    // ─── Drug reaction patterns ───────────────────────────────────────────────
    case 'drug_reactions':
        $stmt = $conn->prepare("
            SELECT
                m.name              AS medicine_name,
                dr.severity,
                COUNT(*)            AS report_count,
                GROUP_CONCAT(dr.reaction SEPARATOR ' | ') AS reactions
            FROM drug_reactions dr
            JOIN medicine_batches mb ON dr.batch_id    = mb.batch_id
            JOIN medicines m         ON mb.medicine_id = m.medicine_id
            GROUP BY m.medicine_id, dr.severity
            ORDER BY report_count DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["status" => "success", "data" => $rows]);
        break;

    default:
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Unknown report type"]);
}
?>
