-- ============================================================
-- BoMRA System — Full Database Schema
-- Matches all document requirements:
--   - Medicine registration & batch management
--   - Pharmacy/facility inspections (scheduled + results)
--   - License management (issue, renew, suspend)
--   - Drug reaction reports
--   - Delivery & stock tracking
--   - User management with roles
--   - Reports and statistics
-- ============================================================

CREATE DATABASE IF NOT EXISTS bomra_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bomra_system;

-- ─── USERS ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('admin', 'supplier', 'facility', 'inspector') NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── SUPPLIERS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL UNIQUE,
    name        VARCHAR(150) NOT NULL,
    contact     VARCHAR(100),
    address     TEXT,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ─── FACILITIES (pharmacies / hospitals) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS facilities (
    facility_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL UNIQUE,
    name        VARCHAR(150) NOT NULL,
    address     TEXT,
    contact     VARCHAR(100),
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ─── MEDICINES ────────────────────────────────────────────────────────────────
-- Stores the medicine master record (name + manufacturer)
CREATE TABLE IF NOT EXISTS medicines (
    medicine_id  INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    manufacturer VARCHAR(200) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medicine (name, manufacturer)
);

-- ─── MEDICINE BATCHES ─────────────────────────────────────────────────────────
-- Document: "batch number, expiry date, manufacturer, supplier, approval status"
CREATE TABLE IF NOT EXISTS medicine_batches (
    batch_id     INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id  INT          NOT NULL,
    supplier_id  INT          NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    quantity     INT          NOT NULL CHECK (quantity > 0),
    expiry_date  DATE         NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
);

-- ─── APPLICATIONS (medicine registration) ────────────────────────────────────
-- Document: "assigns a status, sends it for evaluation, allows users to track progress"
CREATE TABLE IF NOT EXISTS applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id       INT         NOT NULL,
    submitted_by   INT         NOT NULL,
    reviewed_by    INT,
    status         ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    review_notes   TEXT,
    review_date    DATETIME,
    created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id)     REFERENCES medicine_batches(batch_id),
    FOREIGN KEY (submitted_by) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by)  REFERENCES users(user_id)
);

-- ─── CERTIFICATES ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS certificates (
    certificate_id     INT AUTO_INCREMENT PRIMARY KEY,
    batch_id           INT         NOT NULL UNIQUE,
    issued_by          INT         NOT NULL,
    certificate_number VARCHAR(30) NOT NULL UNIQUE,
    issued_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id)  REFERENCES medicine_batches(batch_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- ─── LICENSES ─────────────────────────────────────────────────────────────────
-- Document: "issues, renews, suspends licenses for pharmacies or suppliers"
CREATE TABLE IF NOT EXISTS licenses (
    license_id     INT AUTO_INCREMENT PRIMARY KEY,
    license_number VARCHAR(30)  NOT NULL UNIQUE,
    holder_type    ENUM('facility', 'supplier') NOT NULL,
    holder_id      INT          NOT NULL,
    issued_by      INT          NOT NULL,
    status         ENUM('active', 'suspended', 'expired') NOT NULL DEFAULT 'active',
    expires_at     DATE,
    issued_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- ─── INSPECTIONS ──────────────────────────────────────────────────────────────
-- Document: "schedules inspections, records findings, evaluates results"
CREATE TABLE IF NOT EXISTS inspections (
    inspection_id  INT AUTO_INCREMENT PRIMARY KEY,
    facility_id    INT          NOT NULL,
    inspector_id   INT          NOT NULL,
    status         ENUM('scheduled', 'passed', 'failed') NOT NULL,
    notes          TEXT,
    scheduled_date DATE,
    inspected_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id)  REFERENCES facilities(facility_id),
    FOREIGN KEY (inspector_id) REFERENCES users(user_id)
);

-- ─── REQUESTS (facility supply requests) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS requests (
    request_id   INT AUTO_INCREMENT PRIMARY KEY,
    facility_id  INT      NOT NULL,
    requested_by INT      NOT NULL,
    status       ENUM('open', 'fulfilled') NOT NULL DEFAULT 'open',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id)  REFERENCES facilities(facility_id),
    FOREIGN KEY (requested_by) REFERENCES users(user_id)
);

-- ─── DELIVERIES ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS deliveries (
    delivery_id  INT AUTO_INCREMENT PRIMARY KEY,
    request_id   INT      NOT NULL,
    supplier_id  INT      NOT NULL,
    status       ENUM('shipped', 'delivered') NOT NULL DEFAULT 'shipped',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id)  REFERENCES requests(request_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
);

-- ─── DELIVERY ITEMS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS delivery_items (
    item_id     INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    batch_id    INT NOT NULL,
    quantity    INT NOT NULL CHECK (quantity > 0),
    FOREIGN KEY (delivery_id) REFERENCES deliveries(delivery_id),
    FOREIGN KEY (batch_id)    REFERENCES medicine_batches(batch_id)
);

-- ─── STOCK ────────────────────────────────────────────────────────────────────
-- Document: "monitor stock, detect expired medicines, identify counterfeit drugs"
CREATE TABLE IF NOT EXISTS stock (
    stock_id    INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    batch_id    INT NOT NULL,
    quantity    INT NOT NULL DEFAULT 0,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stock (facility_id, batch_id),
    FOREIGN KEY (facility_id) REFERENCES facilities(facility_id),
    FOREIGN KEY (batch_id)    REFERENCES medicine_batches(batch_id)
);

-- ─── DRUG REACTIONS ───────────────────────────────────────────────────────────
-- Document: "captures adverse drug reaction reports, validates, analyzes patterns"
CREATE TABLE IF NOT EXISTS drug_reactions (
    report_id   INT AUTO_INCREMENT PRIMARY KEY,
    batch_id    INT         NOT NULL,
    reported_by INT         NOT NULL,
    reaction    TEXT        NOT NULL,
    severity    ENUM('mild', 'moderate', 'severe') NOT NULL,
    notes       TEXT,
    reported_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id)    REFERENCES medicine_batches(batch_id),
    FOREIGN KEY (reported_by) REFERENCES users(user_id)
);

-- ─── SEED ADMIN ACCOUNTS ─────────────────────────────────────────────────────
-- Generate hashes: php -r "echo password_hash('YourPassword1!', PASSWORD_DEFAULT);"
INSERT INTO users (name, email, password, role) VALUES
  ('Percy',    'percy@bomra.bw',    '$2y$12$PLACEHOLDER_HASH_PERCY',    'admin'),
  ('Yoliswa',  'yoliswa@bomra.bw',  '$2y$12$PLACEHOLDER_HASH_YOLISWA',  'admin'),
  ('Patso',    'patso@bomra.bw',    '$2y$12$PLACEHOLDER_HASH_PATSO',    'admin'),
  ('Mphoyame', 'mphoyame@bomra.bw', '$2y$12$PLACEHOLDER_HASH_MPHOYAME', 'admin')
ON DUPLICATE KEY UPDATE role = 'admin';
