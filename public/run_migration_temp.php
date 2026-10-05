<?php
// ONE-TIME migration runner — DELETE THIS FILE AFTER USE
if (($_GET['key'] ?? '') !== 'leave_alloc_2026') { http_response_code(403); exit('Forbidden'); }

$dsn  = 'mysql:host=localhost;dbname=u536910280_EMSS;charset=utf8mb4';
$user = 'u536910280_EMSS';
$pass = 'Bareen1214!@';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $results = [];

    $sqls = [
        "ALTER TABLE `leave_types` ADD COLUMN IF NOT EXISTS `default_days` DECIMAL(8,2) NOT NULL DEFAULT 0",
        "ALTER TABLE `leave_types` ADD COLUMN IF NOT EXISTS `accrual_type` ENUM('yearly','monthly') NOT NULL DEFAULT 'yearly'",
        "INSERT INTO `leave_types` (company_id,name,code,description,is_paid,requires_approval,allow_half_day,default_days,accrual_type,color,sort_order,is_active)
         SELECT c.id,'Annual Leave','AL','Standard annual leave entitlement',1,1,1,15.00,'yearly','#2563eb',1,1
         FROM companies c WHERE c.deleted_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.company_id=c.id AND lt.code='AL' AND lt.deleted_at IS NULL)",
        "INSERT INTO `leave_types` (company_id,name,code,description,is_paid,requires_approval,allow_half_day,default_days,accrual_type,color,sort_order,is_active)
         SELECT c.id,'Sick Leave','SL','1 day credited per month',1,0,1,1.00,'monthly','#16a34a',2,1
         FROM companies c WHERE c.deleted_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.company_id=c.id AND lt.code='SL' AND lt.deleted_at IS NULL)",
        "INSERT INTO `leave_types` (company_id,name,code,description,is_paid,requires_approval,allow_half_day,default_days,accrual_type,color,sort_order,is_active)
         SELECT c.id,'Casual Leave','CL','1 day credited per month',1,1,1,1.00,'monthly','#d97706',3,1
         FROM companies c WHERE c.deleted_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.company_id=c.id AND lt.code='CL' AND lt.deleted_at IS NULL)",
    ];

    foreach ($sqls as $sql) {
        $rows = $pdo->exec($sql);
        $results[] = ['sql' => substr($sql, 0, 60) . '...', 'affected' => $rows];
    }

    echo '<pre style="font-family:monospace;padding:20px">';
    echo "Migration completed successfully!\n\n";
    foreach ($results as $r) {
        echo "OK [{$r['affected']} rows] " . $r['sql'] . "\n";
    }
    echo "\n\nDELETE THIS FILE: /public/run_migration_temp.php";
    echo '</pre>';
} catch (Exception $e) {
    http_response_code(500);
    echo '<pre style="color:red">ERROR: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
