-- Add default allocation columns to leave_types
ALTER TABLE `leave_types`
    ADD COLUMN IF NOT EXISTS `default_days` DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'Default days allocated per accrual period',
    ADD COLUMN IF NOT EXISTS `accrual_type` ENUM('yearly','monthly') NOT NULL DEFAULT 'yearly' COMMENT 'How often default_days is credited';

-- Seed default leave types for every existing company that doesn't already have them
INSERT INTO `leave_types` (company_id, name, code, description, is_paid, requires_approval, allow_half_day, default_days, accrual_type, color, sort_order, is_active)
SELECT
    c.id,
    'Annual Leave',
    'AL',
    'Standard annual leave entitlement',
    1, 1, 1,
    15.00,
    'yearly',
    '#2563eb',
    1,
    1
FROM companies c
WHERE c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM leave_types lt WHERE lt.company_id = c.id AND lt.code = 'AL' AND lt.deleted_at IS NULL
  );

INSERT INTO `leave_types` (company_id, name, code, description, is_paid, requires_approval, allow_half_day, default_days, accrual_type, color, sort_order, is_active)
SELECT
    c.id,
    'Sick Leave',
    'SL',
    '1 day credited per month',
    1, 0, 1,
    1.00,
    'monthly',
    '#16a34a',
    2,
    1
FROM companies c
WHERE c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM leave_types lt WHERE lt.company_id = c.id AND lt.code = 'SL' AND lt.deleted_at IS NULL
  );

INSERT INTO `leave_types` (company_id, name, code, description, is_paid, requires_approval, allow_half_day, default_days, accrual_type, color, sort_order, is_active)
SELECT
    c.id,
    'Casual Leave',
    'CL',
    '1 day credited per month',
    1, 1, 1,
    1.00,
    'monthly',
    '#d97706',
    3,
    1
FROM companies c
WHERE c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM leave_types lt WHERE lt.company_id = c.id AND lt.code = 'CL' AND lt.deleted_at IS NULL
  );
