CREATE TABLE IF NOT EXISTS `planned_off_days` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   BIGINT UNSIGNED NOT NULL,
  `employee_id`  BIGINT UNSIGNED NOT NULL,
  `off_date`     DATE NOT NULL,
  `reason`       VARCHAR(255) NOT NULL DEFAULT '',
  `note`         TEXT DEFAULT NULL,
  `created_by`   BIGINT UNSIGNED DEFAULT NULL,
  `notified_at`  DATETIME DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pod_company`  (`company_id`),
  KEY `idx_pod_employee` (`employee_id`),
  KEY `idx_pod_date`     (`off_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
