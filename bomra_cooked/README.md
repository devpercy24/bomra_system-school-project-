# BoMRA System — Backend API Reference

## Architecture
Three-tier: HTML/CSS/JS frontend → PHP backend (Application Tier) → MySQL (Data Tier)

---

## Auth Endpoints

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| POST | `/api/auth/register.php` | Public | Register supplier / facility / inspector |
| POST | `/api/auth/login.php` | Public | Login and start session |
| POST | `/api/auth/logout.php` | Authenticated | End session |

### Register body
```json
{ "name": "Acme Pharma", "email": "acme@mail.com", "password": "Strong1!", "role": "supplier" }
```
Roles allowed: `supplier`, `facility`, `inspector` (admins are seeded only).

---

## Admin Endpoints (require admin session)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/get_applications.php` | List all medicine registration applications with batch details |
| POST | `/api/admin/review_application.php` | Approve or reject an application |
| POST | `/api/admin/issue_certificate.php` | Issue certificate for an approved batch |
| POST | `/api/admin/manage_license.php` | Issue / renew / suspend a license |
| GET | `/api/admin/get_licenses.php` | List all licenses |
| GET/POST | `/api/admin/manage_users.php` | List users (GET) or change a user's role (POST) |

### review_application body
```json
{ "application_id": 1, "status": "approved", "notes": "All documents verified" }
```

### issue_certificate body
```json
{ "batch_id": 3 }
```

### manage_license — issue
```json
{ "action": "issue", "holder_type": "facility", "holder_id": 2, "expires_at": "2027-01-01" }
```
### manage_license — renew
```json
{ "action": "renew", "license_id": 1, "expires_at": "2028-01-01" }
```
### manage_license — suspend
```json
{ "action": "suspend", "license_id": 1 }
```

---

## Supplier Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/supplier/add_batch.php` | Add medicine + batch (creates medicine record if new) |
| POST | `/api/supplier/submit_application.php` | Submit registration application for a batch |
| POST | `/api/supplier/create_delivery.php` | Create a delivery for a facility request |
| POST | `/api/supplier/add_delivery_item.php` | Add a batch item to a delivery |
| POST | `/api/supplier/complete_delivery.php` | Mark delivery complete and update facility stock |

### add_batch body
```json
{
  "medicine_name": "Paracetamol 500mg",
  "manufacturer": "PharmaCorp",
  "batch_number": "PC-2025-001",
  "expiry_date": "2027-06-30",
  "quantity": 5000
}
```

---

## Facility Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/facility/create_request.php` | Create a supply request |
| GET | `/api/facility/get_stock.php` | View own stock with expiry status flags |

Stock response includes `expiry_status`: `ok` | `expiring_soon` (< 90 days) | `expired`

---

## Inspector Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/inspector/add_inspection.php` | Record or schedule an inspection |
| GET | `/api/inspector/get_inspections.php` | View own inspection history |

### add_inspection body
```json
{ "facility_id": 2, "status": "passed", "notes": "All standards met" }
```
For scheduling: `"status": "scheduled"` + `"scheduled_date": "2025-09-15"`

---

## Reports Endpoints (admin only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reports/statistics.php?type=summary` | Dashboard counts |
| GET | `/api/reports/statistics.php?type=inspections` | All inspections report |
| GET | `/api/reports/statistics.php?type=expired_medicines` | Expired medicines in stock |
| GET | `/api/reports/statistics.php?type=drug_reactions` | Reaction pattern analysis |
| POST | `/api/reports/drug_reactions.php` | Submit adverse drug reaction report |
| GET | `/api/reports/drug_reactions.php` | List all reaction reports (admin / inspector) |

---

## Database Setup

```bash
mysql -u root -p < schema.sql
php generate_admin_hashes.php    # then paste hashes into schema.sql and re-run
```

## Security Features
- Passwords hashed with `PASSWORD_DEFAULT` (bcrypt)
- All DB queries use prepared statements (no SQL injection)
- Session hardening: HttpOnly, Secure, SameSite=Strict, 30-min timeout, session fixation prevention
- Admin whitelist: only percy / yoliswa / patso / mphoyame can hold the admin role
- Role-based access on every protected endpoint
- HTTP-only: role-specific actions return 403 for unauthorised roles
