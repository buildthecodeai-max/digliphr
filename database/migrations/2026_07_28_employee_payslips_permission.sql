-- Grant employee self-service payslip access (safe to re-run)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'employee'
  AND p.slug = 'payslips.view';
