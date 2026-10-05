-- Product and UX enhancement support tables.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS company_setup_progress (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  dismissed_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  completed_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_company_setup_progress (company_id),
  CONSTRAINT fk_company_setup_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_company_setup_user FOREIGN KEY (completed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_filters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  module VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  filters JSON NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_saved_filters_user_module_name (user_id, module, name),
  KEY idx_saved_filters_company_module (company_id, module),
  CONSTRAINT fk_saved_filters_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_saved_filters_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_import_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED DEFAULT NULL,
  original_filename VARCHAR(255) NOT NULL,
  status ENUM('previewed','importing','completed','completed_with_errors','failed') NOT NULL DEFAULT 'previewed',
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
  imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
  error_rows INT UNSIGNED NOT NULL DEFAULT 0,
  preview_data JSON DEFAULT NULL,
  error_data JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_employee_import_company (company_id, created_at),
  CONSTRAINT fk_employee_import_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_employee_import_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_workflows (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  workflow_type ENUM('onboarding','offboarding') NOT NULL,
  status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'in_progress',
  target_date DATE DEFAULT NULL,
  started_by BIGINT UNSIGNED DEFAULT NULL,
  completed_by BIGINT UNSIGNED DEFAULT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employee_workflows_employee (employee_id, workflow_type, status),
  KEY idx_employee_workflows_company (company_id, status),
  CONSTRAINT fk_employee_workflows_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_employee_workflows_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_employee_workflows_started_by FOREIGN KEY (started_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_employee_workflows_completed_by FOREIGN KEY (completed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_workflow_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  workflow_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description VARCHAR(1000) DEFAULT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'general',
  assigned_user_id BIGINT UNSIGNED DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  status ENUM('pending','completed','skipped') NOT NULL DEFAULT 'pending',
  sort_order INT NOT NULL DEFAULT 0,
  completed_by BIGINT UNSIGNED DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_workflow_tasks_workflow (workflow_id, status, sort_order),
  CONSTRAINT fk_workflow_tasks_workflow FOREIGN KEY (workflow_id) REFERENCES employee_workflows(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_workflow_tasks_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_workflow_tasks_completed_by FOREIGN KEY (completed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_chains (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  module ENUM('leave','attendance','overtime','loan','payroll') NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  escalation_hours INT UNSIGNED NOT NULL DEFAULT 24,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_approval_chains_company_module_name (company_id, module, name),
  KEY idx_approval_chains_active (company_id, module, is_active),
  CONSTRAINT fk_approval_chains_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_approval_chains_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_chain_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  approval_chain_id BIGINT UNSIGNED NOT NULL,
  step_order INT UNSIGNED NOT NULL,
  approver_type ENUM('role','manager','user') NOT NULL DEFAULT 'role',
  role_slug VARCHAR(100) DEFAULT NULL,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  label VARCHAR(150) NOT NULL,
  reminder_hours INT UNSIGNED NOT NULL DEFAULT 24,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_approval_chain_step_order (approval_chain_id, step_order),
  CONSTRAINT fk_approval_steps_chain FOREIGN KEY (approval_chain_id) REFERENCES approval_chains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_approval_steps_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_reminders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  module VARCHAR(50) NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  reminded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_approval_reminders_record (company_id, module, record_id),
  CONSTRAINT fk_approval_reminders_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_approval_reminders_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_approval_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payroll_period_id BIGINT UNSIGNED NOT NULL,
  reviewed_by BIGINT UNSIGNED NOT NULL,
  variance_snapshot JSON NOT NULL,
  acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payroll_reviews_period (payroll_period_id, acknowledged_at),
  CONSTRAINT fk_payroll_reviews_period FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_payroll_reviews_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sso_identities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(80) NOT NULL DEFAULT 'oidc',
  provider_subject VARCHAR(191) NOT NULL,
  email VARCHAR(191) DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sso_provider_subject (provider, provider_subject),
  KEY idx_sso_user (user_id),
  CONSTRAINT fk_sso_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sso_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (company_id, group_name, setting_key, setting_value, value_type, description)
SELECT c.id, 'branding', defaults.setting_key, defaults.setting_value, defaults.value_type, defaults.description
FROM companies c
CROSS JOIN (
  SELECT 'login_tagline' setting_key, 'People operations, without the busywork.' setting_value, 'string' value_type, 'Shown on the company login page.' description
  UNION ALL SELECT 'login_accent', '#4f46e5', 'string', 'Login page accent color.'
  UNION ALL SELECT 'sso_enabled', '0', 'boolean', 'Allow OpenID Connect sign-in.'
  UNION ALL SELECT 'sso_issuer', '', 'string', 'OpenID Connect issuer base URL.'
  UNION ALL SELECT 'sso_client_id', '', 'string', 'OpenID Connect client ID.'
  UNION ALL SELECT 'sso_client_secret', '', 'string', 'OpenID Connect client secret.'
  UNION ALL SELECT 'sso_authorize_url', '', 'string', 'Authorization endpoint.'
  UNION ALL SELECT 'sso_token_url', '', 'string', 'Token endpoint.'
  UNION ALL SELECT 'sso_userinfo_url', '', 'string', 'UserInfo endpoint.'
) defaults
WHERE c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM system_settings s
    WHERE s.company_id = c.id AND s.setting_key = defaults.setting_key
  );
