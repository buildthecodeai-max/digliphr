-- =====================================================
-- Biometric Device & QR Punch Integration
-- 2026-10-01
-- =====================================================

-- 1. Add device_pin to employees (used to match ZKTeco punch to employee)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'device_pin'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE employees ADD COLUMN device_pin VARCHAR(20) NULL DEFAULT NULL AFTER employee_code',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Biometric / RFID hardware devices
CREATE TABLE IF NOT EXISTS biometric_devices (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id   BIGINT UNSIGNED NOT NULL,
    serial_number VARCHAR(100) NOT NULL,
    name         VARCHAR(100) NOT NULL,
    location     VARCHAR(200) DEFAULT NULL,
    device_type  ENUM('fingerprint','rfid','face','multi') NOT NULL DEFAULT 'fingerprint',
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_seen_at DATETIME DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at   DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_serial (serial_number),
    KEY idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Raw punch logs from hardware devices
CREATE TABLE IF NOT EXISTS biometric_device_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id     BIGINT UNSIGNED DEFAULT NULL,
    serial_number VARCHAR(100) NOT NULL,
    company_id    BIGINT UNSIGNED DEFAULT NULL,
    employee_id   BIGINT UNSIGNED DEFAULT NULL,
    device_pin    VARCHAR(50) DEFAULT NULL,
    punch_time    DATETIME NOT NULL,
    punch_type    ENUM('check_in','check_out','break_out','break_in','overtime_in','overtime_out') NOT NULL DEFAULT 'check_in',
    verify_type   ENUM('fingerprint','card','password','face','other') NOT NULL DEFAULT 'other',
    raw_data      TEXT DEFAULT NULL,
    status        ENUM('matched','unmatched','processed') NOT NULL DEFAULT 'unmatched',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_device (device_id),
    KEY idx_company (company_id),
    KEY idx_employee (employee_id),
    KEY idx_punch_time (punch_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. QR punch tokens (one per company / office location)
CREATE TABLE IF NOT EXISTS qr_punch_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    token      VARCHAR(64) NOT NULL,
    name       VARCHAR(100) NOT NULL DEFAULT 'Main Office',
    location   VARCHAR(200) DEFAULT NULL,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_token (token),
    KEY idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
