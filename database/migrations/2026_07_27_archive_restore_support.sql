-- Safe archive/restore metadata (nullable; no data loss)
-- Run once against employee_management

ALTER TABLE attendance
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN deletion_reason VARCHAR(500) NULL AFTER deleted_by;

ALTER TABLE leave_requests
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN deletion_reason VARCHAR(500) NULL AFTER deleted_by;

ALTER TABLE payroll_periods
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN deletion_reason VARCHAR(500) NULL AFTER deleted_by;

-- Allow archived + active coexistence for same employee/date
ALTER TABLE attendance
  ADD COLUMN is_active_flag TINYINT
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED;
ALTER TABLE attendance DROP INDEX uk_attendance_employee_date;
ALTER TABLE attendance ADD UNIQUE KEY uk_attendance_active_employee_date (employee_id, attendance_date, is_active_flag);

CREATE INDEX idx_attendance_deleted_at ON attendance (deleted_at);
CREATE INDEX idx_leave_requests_deleted_at ON leave_requests (deleted_at);
CREATE INDEX idx_payroll_periods_deleted_at ON payroll_periods (deleted_at);

CREATE TABLE IF NOT EXISTS leave_amendments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  leave_request_id BIGINT UNSIGNED NOT NULL,
  previous_values JSON NULL,
  new_values JSON NULL,
  reason VARCHAR(1000) NOT NULL,
  balance_adjustment DECIMAL(10,2) NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_leave_amendments_request (leave_request_id),
  CONSTRAINT fk_leave_amendments_request FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
