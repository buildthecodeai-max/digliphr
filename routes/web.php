<?php

declare(strict_types=1);

use App\Core\Application;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\EmployeeMiddleware;
use App\Middleware\GuestMiddleware;

$app = Application::getInstance();
$router = $app->router();

$router->get('/health', ['HealthController', 'check']);

// ZKTeco ADMS biometric device endpoints (no auth — validated by device serial number)
$router->get('/iclock/cdata', ['Api\\ZKTecoController', 'handshake']);
$router->post('/iclock/cdata', ['Api\\ZKTecoController', 'push']);
$router->get('/iclock/getrequest', ['Api\\ZKTecoController', 'getRequest']);
$router->post('/iclock/devicecmd', ['Api\\ZKTecoController', 'deviceCmd']);

$router->get('/', function () use ($app) {
    $auth = new \App\Services\AuthService();
    if ($auth->check()) {
        $app->response()->redirect($auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
    }
    $app->response()->redirect('/login');
});

$router->get('/install', function () {
    require dirname(__DIR__) . '/install/index.php';
    exit;
});
$router->post('/install', function () {
    require dirname(__DIR__) . '/install/index.php';
    exit;
});

// Auth (guest)
$router->group(['middleware' => [GuestMiddleware::class]], function ($router) {
    $router->get('/login', ['Auth\\AuthController', 'showLogin'])->name('login');
    $router->post('/login', ['Auth\\AuthController', 'login'], [CsrfMiddleware::class]);
    $router->get('/auth/sso', ['Auth\\SsoController', 'redirect']);
    $router->get('/auth/sso/callback', ['Auth\\SsoController', 'callback']);
    $router->get('/forgot-password', ['Auth\\AuthController', 'showForgotPassword']);
    $router->post('/forgot-password', ['Auth\\AuthController', 'forgotPassword'], [CsrfMiddleware::class]);
    $router->get('/reset-password/{token}', ['Auth\\AuthController', 'showResetPassword']);
    $router->post('/reset-password', ['Auth\\AuthController', 'resetPassword'], [CsrfMiddleware::class]);
});

// Auth (authenticated)
$router->group(['middleware' => [AuthMiddleware::class]], function ($router) {
    $router->post('/logout', ['Auth\\AuthController', 'logout'], [CsrfMiddleware::class]);
    $router->get('/change-password', ['Auth\\AuthController', 'showChangePassword']);
    $router->post('/change-password', ['Auth\\AuthController', 'changePassword'], [CsrfMiddleware::class]);
    $router->get('/files/{type}/{id}', ['FileController', 'serve']);

    // Team Chat (shared surface for all authenticated users)
    $router->get('/chat', ['ChatController', 'index'])->name('chat');
    $router->get('/chat/overview', ['ChatController', 'overview']);
    $router->get('/chat/files', ['ChatController', 'files']);
    $router->get('/chat/saved', ['ChatController', 'saved']);
    $router->get('/chat/mentions', ['ChatController', 'mentions']);
    $router->get('/chat/notifications', ['ChatController', 'notifications']);
    $router->post('/chat/notifications', ['ChatController', 'notifications'], [CsrfMiddleware::class]);
    $router->get('/chat/documents', ['ChatController', 'documents']);
    $router->post('/chat/documents', ['ChatController', 'storeDocument'], [CsrfMiddleware::class]);
    $router->post('/chat/documents/{id}/acknowledge', ['ChatController', 'acknowledgeDocument'], [CsrfMiddleware::class]);
});

// Admin area
$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class, AdminMiddleware::class]], function ($router) {
    $router->get('/dashboard', ['Admin\\DashboardController', 'index'])->name('admin.dashboard');
    $router->get('/setup', ['Admin\\ProductSetupController', 'index']);
    $router->post('/setup/dismiss', ['Admin\\ProductSetupController', 'dismiss'], [CsrfMiddleware::class]);
    $router->get('/approvals', ['Admin\\ApprovalInboxController', 'index']);
    $router->post('/approvals/remind', ['Admin\\ApprovalInboxController', 'remind'], [CsrfMiddleware::class]);
    $router->get('/approval-chains', ['Admin\\ApprovalChainController', 'index']);
    $router->post('/approval-chains', ['Admin\\ApprovalChainController', 'store'], [CsrfMiddleware::class]);
    $router->post('/approval-chains/{id}', ['Admin\\ApprovalChainController', 'update'], [CsrfMiddleware::class]);
    $router->post('/approval-chains/{id}/delete', ['Admin\\ApprovalChainController', 'destroy'], [CsrfMiddleware::class]);
    $router->post('/saved-filters/{module}', ['Admin\\SavedFilterController', 'store'], [CsrfMiddleware::class]);
    $router->post('/saved-filters/{id}/delete', ['Admin\\SavedFilterController', 'destroy'], [CsrfMiddleware::class]);

    // Companies
    $router->get('/companies', ['Admin\\CompanyController', 'index']);
    $router->get('/companies/create', ['Admin\\CompanyController', 'create']);
    $router->post('/companies', ['Admin\\CompanyController', 'store'], [CsrfMiddleware::class]);
    $router->get('/companies/{id}/edit', ['Admin\\CompanyController', 'edit']);
    $router->post('/companies/{id}', ['Admin\\CompanyController', 'update'], [CsrfMiddleware::class]);
    $router->put('/companies/{id}', ['Admin\\CompanyController', 'update'], [CsrfMiddleware::class]);
    $router->post('/companies/{id}/delete', ['Admin\\CompanyController', 'destroy'], [CsrfMiddleware::class]);

    // Branches
    $router->get('/branches', ['Admin\\BranchController', 'index']);
    $router->get('/branches/create', ['Admin\\BranchController', 'create']);
    $router->post('/branches', ['Admin\\BranchController', 'store'], [CsrfMiddleware::class]);
    $router->get('/branches/{id}/edit', ['Admin\\BranchController', 'edit']);
    $router->post('/branches/{id}', ['Admin\\BranchController', 'update'], [CsrfMiddleware::class]);
    $router->put('/branches/{id}', ['Admin\\BranchController', 'update'], [CsrfMiddleware::class]);
    $router->post('/branches/{id}/delete', ['Admin\\BranchController', 'destroy'], [CsrfMiddleware::class]);

    // Departments
    $router->get('/departments', ['Admin\\DepartmentController', 'index']);
    $router->get('/departments/create', ['Admin\\DepartmentController', 'create']);
    $router->post('/departments', ['Admin\\DepartmentController', 'store'], [CsrfMiddleware::class]);
    $router->get('/departments/{id}/edit', ['Admin\\DepartmentController', 'edit']);
    $router->post('/departments/{id}', ['Admin\\DepartmentController', 'update'], [CsrfMiddleware::class]);
    $router->put('/departments/{id}', ['Admin\\DepartmentController', 'update'], [CsrfMiddleware::class]);
    $router->post('/departments/{id}/delete', ['Admin\\DepartmentController', 'destroy'], [CsrfMiddleware::class]);

    // Designations
    $router->get('/designations', ['Admin\\DesignationController', 'index']);
    $router->get('/designations/create', ['Admin\\DesignationController', 'create']);
    $router->post('/designations', ['Admin\\DesignationController', 'store'], [CsrfMiddleware::class]);
    $router->get('/designations/{id}/edit', ['Admin\\DesignationController', 'edit']);
    $router->post('/designations/{id}', ['Admin\\DesignationController', 'update'], [CsrfMiddleware::class]);
    $router->put('/designations/{id}', ['Admin\\DesignationController', 'update'], [CsrfMiddleware::class]);
    $router->post('/designations/{id}/delete', ['Admin\\DesignationController', 'destroy'], [CsrfMiddleware::class]);

    // Weekly schedule patterns
    $router->get('/week-patterns', ['Admin\\AttendanceWeekPatternController', 'index']);
    $router->get('/week-patterns/create', ['Admin\\AttendanceWeekPatternController', 'create']);
    $router->post('/week-patterns', ['Admin\\AttendanceWeekPatternController', 'store'], [CsrfMiddleware::class]);
    $router->get('/week-patterns/{id}/edit', ['Admin\\AttendanceWeekPatternController', 'edit']);
    $router->post('/week-patterns/{id}', ['Admin\\AttendanceWeekPatternController', 'update'], [CsrfMiddleware::class]);
    $router->post('/week-patterns/{id}/delete', ['Admin\\AttendanceWeekPatternController', 'destroy'], [CsrfMiddleware::class]);

    // Employees
    $router->get('/employees', ['Admin\\EmployeeController', 'index']);
    $router->get('/employees/export', ['Admin\\EmployeeController', 'export']);
    $router->get('/employees/import', ['Admin\\EmployeeImportController', 'index']);
    $router->post('/employees/import/preview', ['Admin\\EmployeeImportController', 'preview'], [CsrfMiddleware::class]);
    $router->post('/employees/import/{id}/confirm', ['Admin\\EmployeeImportController', 'confirm'], [CsrfMiddleware::class]);
    $router->get('/employees/import/{id}/errors', ['Admin\\EmployeeImportController', 'errors']);
    $router->get('/employees-import-template', ['Admin\\EmployeeImportController', 'template']);
    $router->get('/employees/create', ['Admin\\EmployeeController', 'create']);
    $router->post('/employees', ['Admin\\EmployeeController', 'store'], [CsrfMiddleware::class]);
    $router->get('/employees/{id}', ['Admin\\EmployeeController', 'show']);
    $router->get('/employees/{id}/edit', ['Admin\\EmployeeController', 'edit']);
    $router->post('/employees/{id}', ['Admin\\EmployeeController', 'update'], [CsrfMiddleware::class]);
    $router->post('/employees/{id}/delete', ['Admin\\EmployeeController', 'destroy'], [CsrfMiddleware::class]);
    $router->delete('/employees/{id}', ['Admin\\EmployeeController', 'destroy'], [CsrfMiddleware::class]);
    $router->post('/employees/{id}/reactivate', ['Admin\\EmployeeController', 'reactivate'], [CsrfMiddleware::class]);
    $router->post('/employees/{id}/status', ['Admin\\EmployeeController', 'changeStatus'], [CsrfMiddleware::class]);
    $router->post('/employees/{id}/create-login', ['Admin\\EmployeeController', 'createLogin'], [CsrfMiddleware::class]);
    $router->get('/employees/{employeeId}/attendance-device', ['Admin\\AttendanceDeviceController', 'show']);
    $router->post('/employees/{employeeId}/attendance-device/{deviceId}/approve', ['Admin\\AttendanceDeviceController', 'approve'], [CsrfMiddleware::class]);
    $router->post('/employees/{employeeId}/attendance-device/{deviceId}/reject', ['Admin\\AttendanceDeviceController', 'reject'], [CsrfMiddleware::class]);
    $router->post('/employees/{employeeId}/attendance-device/{deviceId}/revoke', ['Admin\\AttendanceDeviceController', 'revoke'], [CsrfMiddleware::class]);
    $router->post('/employees/{employeeId}/attendance-device/reset', ['Admin\\AttendanceDeviceController', 'reset'], [CsrfMiddleware::class]);
    $router->get('/employees/{employeeId}/workflows', ['Admin\\EmployeeWorkflowController', 'show']);
    $router->post('/employees/{employeeId}/workflows', ['Admin\\EmployeeWorkflowController', 'start'], [CsrfMiddleware::class]);
    $router->post('/employees/{employeeId}/workflow-tasks/{taskId}', ['Admin\\EmployeeWorkflowController', 'task'], [CsrfMiddleware::class]);
    $router->put('/employees/{id}', ['Admin\\EmployeeController', 'update'], [CsrfMiddleware::class]);


    // Shifts
    $router->get('/shifts', ['Admin\\ShiftController', 'index']);
    $router->get('/shifts/create', ['Admin\\ShiftController', 'create']);
    $router->post('/shifts', ['Admin\\ShiftController', 'store'], [CsrfMiddleware::class]);
    $router->get('/shifts/{id}/edit', ['Admin\\ShiftController', 'edit']);
    $router->post('/shifts/{id}', ['Admin\\ShiftController', 'update'], [CsrfMiddleware::class]);
    $router->post('/shifts/{id}/delete', ['Admin\\ShiftController', 'destroy'], [CsrfMiddleware::class]);
    $router->post('/shifts/{id}/assign', ['Admin\\ShiftController', 'assign'], [CsrfMiddleware::class]);

    // Attendance — static paths before {id}
    $router->get('/attendance', ['Admin\\AttendanceController', 'index']);
    $router->get('/attendance/overview', ['Admin\\WorkspaceOverviewController', 'attendance']);
    $router->get('/attendance/archived', ['Admin\\AttendanceController', 'archived']);
    $router->get('/attendance/corrections', ['Admin\\AttendanceController', 'corrections']);
    $router->post('/attendance/corrections/{id}/approve', ['Admin\\AttendanceController', 'approveCorrection'], [CsrfMiddleware::class]);
    $router->post('/attendance/corrections/{id}/reject', ['Admin\\AttendanceController', 'rejectCorrection'], [CsrfMiddleware::class]);
    $router->post('/attendance/corrections/{id}/delete', ['Admin\\AttendanceController', 'deleteCorrection'], [CsrfMiddleware::class]);
    $router->get('/attendance/export', ['Admin\\AttendanceController', 'export']);
    $router->get('/attendance/manual', ['Admin\\AttendanceController', 'manual']);
    $router->post('/attendance/manual', ['Admin\\AttendanceController', 'manual'], [CsrfMiddleware::class]);
    $router->get('/attendance/images/{id}', ['Admin\\AttendanceController', 'image']);
    $router->get('/attendance/{id}', ['Admin\\AttendanceController', 'show']);
    $router->post('/attendance/{id}/update', ['Admin\\AttendanceController', 'update'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/delete', ['Admin\\AttendanceController', 'destroy'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/archive', ['Admin\\AttendanceController', 'destroy'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/restore', ['Admin\\AttendanceController', 'restore'], [CsrfMiddleware::class]);
    $router->get('/attendance/{id}/force-delete', ['Admin\\AttendanceController', 'confirmForceDestroy']);
    $router->post('/attendance/force-delete', ['Admin\\AttendanceController', 'forceDestroy'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/force-delete', ['Admin\\AttendanceController', 'forceDestroy'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/approve', ['Admin\\AttendanceController', 'approve'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/reject', ['Admin\\AttendanceController', 'reject'], [CsrfMiddleware::class]);
    $router->post('/attendance/{id}/correct', ['Admin\\AttendanceController', 'correct'], [CsrfMiddleware::class]);

    // Leave — static paths before {id}
    $router->get('/leave', ['Admin\\LeaveController', 'index']);
    $router->get('/leave/create', ['Admin\\LeaveController', 'createManual']);
    $router->post('/leave', ['Admin\\LeaveController', 'storeManual'], [CsrfMiddleware::class]);
    $router->get('/leave/overview', ['Admin\\WorkspaceOverviewController', 'leave']);
    $router->get('/leave/archived', ['Admin\\LeaveController', 'archived']);
    $router->get('/leave/pending', ['Admin\\LeaveController', 'pending']);
    $router->get('/leave/approved', ['Admin\\LeaveController', 'approved']);
    $router->get('/leave/types', ['Admin\\LeaveController', 'types']);
    $router->get('/leave/types/create', ['Admin\\LeaveController', 'createType']);
    $router->post('/leave/types', ['Admin\\LeaveController', 'storeType'], [CsrfMiddleware::class]);
    $router->get('/leave/types/{id}/edit', ['Admin\\LeaveController', 'editType']);
    $router->post('/leave/types/{id}', ['Admin\\LeaveController', 'updateType'], [CsrfMiddleware::class]);
    $router->post('/leave/types/{id}/delete', ['Admin\\LeaveController', 'destroyType'], [CsrfMiddleware::class]);
    $router->get('/leave/balances', ['Admin\\LeaveController', 'balances']);
    $router->post('/leave/balances', ['Admin\\LeaveController', 'updateBalance'], [CsrfMiddleware::class]);
    $router->get('/leave/allocate', ['Admin\\LeaveController', 'allocate']);
    $router->post('/leave/allocate/run', ['Admin\\LeaveController', 'runAllocation'], [CsrfMiddleware::class]);
    $router->get('/leave/holidays', ['Admin\\LeaveController', 'holidays']);
    $router->get('/leave/holidays/create', ['Admin\\LeaveController', 'createHoliday']);
    $router->post('/leave/holidays', ['Admin\\LeaveController', 'storeHoliday'], [CsrfMiddleware::class]);
    $router->get('/leave/holidays/{id}/edit', ['Admin\\LeaveController', 'editHoliday']);
    $router->post('/leave/holidays/{id}', ['Admin\\LeaveController', 'updateHoliday'], [CsrfMiddleware::class]);
    $router->post('/leave/holidays/{id}/delete', ['Admin\\LeaveController', 'destroyHoliday'], [CsrfMiddleware::class]);
    $router->get('/leave/{id}', ['Admin\\LeaveController', 'show']);
    $router->get('/leave/{id}/edit', ['Admin\\LeaveController', 'edit']);
    $router->post('/leave/{id}/update', ['Admin\\LeaveController', 'update'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/approve', ['Admin\\LeaveController', 'approve'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/reject', ['Admin\\LeaveController', 'reject'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/extend', ['Admin\\LeaveController', 'extend'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/cancel', ['Admin\\LeaveController', 'cancel'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/archive', ['Admin\\LeaveController', 'archive'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/restore', ['Admin\\LeaveController', 'restore'], [CsrfMiddleware::class]);
    $router->get('/leave/{id}/force-delete', ['Admin\\LeaveController', 'confirmForceDestroy']);
    $router->post('/leave/force-delete', ['Admin\\LeaveController', 'forceDestroy'], [CsrfMiddleware::class]);
    $router->post('/leave/{id}/force-delete', ['Admin\\LeaveController', 'forceDestroy'], [CsrfMiddleware::class]);

    // Holidays alias
    $router->get('/holidays', ['Admin\\LeaveController', 'holidays']);

    // Payroll — static paths before {id}
    $router->get('/payroll', ['Admin\\PayrollController', 'index']);
    $router->get('/payroll/overview', ['Admin\\WorkspaceOverviewController', 'payroll']);
    $router->get('/payroll/archived', ['Admin\\PayrollController', 'archived']);
    $router->get('/payroll/create', ['Admin\\PayrollController', 'create']);
    $router->get('/payroll/{id}/variance', ['Admin\\PayrollController', 'variance']);
    $router->post('/payroll', ['Admin\\PayrollController', 'store'], [CsrfMiddleware::class]);
    $router->get('/payroll/{id}', ['Admin\\PayrollController', 'show']);
    $router->post('/payroll/{id}/process', ['Admin\\PayrollController', 'process'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/approve', ['Admin\\PayrollController', 'approve'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/lock', ['Admin\\PayrollController', 'lock'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/reopen', ['Admin\\PayrollController', 'reopen'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/cancel', ['Admin\\PayrollController', 'cancel'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/archive', ['Admin\\PayrollController', 'archive'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/restore', ['Admin\\PayrollController', 'restore'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/force-delete', ['Admin\\PayrollController', 'forceDestroy'], [CsrfMiddleware::class]);
    $router->post('/payroll/{id}/payslips', ['Admin\\PayrollController', 'generatePayslips'], [CsrfMiddleware::class]);
    $router->get('/payroll/{periodId}/records/{recordId}', ['Admin\\PayrollController', 'record']);
    $router->get('/payroll/{periodId}/records/{recordId}/edit', ['Admin\\PayrollController', 'editRecord']);
    $router->post('/payroll/{periodId}/records/{recordId}/update', ['Admin\\PayrollController', 'updateRecord'], [CsrfMiddleware::class]);

    // Salary structures
    $router->get('/salary-structures', ['Admin\\SalaryStructureController', 'index']);
    $router->get('/salary-structures/create', ['Admin\\SalaryStructureController', 'create']);
    $router->post('/salary-structures', ['Admin\\SalaryStructureController', 'store'], [CsrfMiddleware::class]);
    $router->get('/salary-structures/{id}/edit', ['Admin\\SalaryStructureController', 'edit']);
    $router->post('/salary-structures/{id}', ['Admin\\SalaryStructureController', 'update'], [CsrfMiddleware::class]);
    $router->post('/salary-structures/{id}/delete', ['Admin\\SalaryStructureController', 'destroy'], [CsrfMiddleware::class]);

    // Reports
    $router->get('/reports', ['Admin\\ReportController', 'index']);
    $router->get('/reports/builder', ['Admin\\ReportController', 'builder']);
    $router->get('/reports/attendance', ['Admin\\ReportController', 'attendance']);
    $router->get('/reports/attendance/corrections', function() use ($app) {
        $app->response()->redirect('/admin/reports/attendance?section=corrections');
    });
    $router->get('/reports/leave', ['Admin\\ReportController', 'leave']);
    $router->get('/reports/payroll', ['Admin\\ReportController', 'payroll']);
    $router->get('/reports/employees', ['Admin\\ReportController', 'employees']);
    $router->get('/reports/loans', ['Admin\\ReportController', 'loans']);
    $router->get('/reports/documents', ['Admin\\ReportController', 'documents']);
    $router->get('/reports/export/{type}', ['Admin\\ReportController', 'export']);
    $router->post('/reports/attendance/corrections/{id}/approve', ['Admin\\ReportController', 'approveCorrection'], [CsrfMiddleware::class]);
    $router->post('/reports/attendance/corrections/{id}/reject', ['Admin\\ReportController', 'rejectCorrection'], [CsrfMiddleware::class]);

    // Roles
    $router->get('/roles', ['Admin\\RoleController', 'index']);
    $router->get('/roles/create', ['Admin\\RoleController', 'create']);
    $router->post('/roles', ['Admin\\RoleController', 'store'], [CsrfMiddleware::class]);
    $router->get('/roles/{id}/edit', ['Admin\\RoleController', 'edit']);
    $router->post('/roles/{id}', ['Admin\\RoleController', 'update'], [CsrfMiddleware::class]);
    $router->post('/roles/{id}/delete', ['Admin\\RoleController', 'destroy'], [CsrfMiddleware::class]);

    // Users
    $router->get('/users', ['Admin\\UserController', 'index']);
    $router->get('/users/create', ['Admin\\UserController', 'create']);
    $router->post('/users', ['Admin\\UserController', 'store'], [CsrfMiddleware::class]);
    $router->post('/users/reset-all-passwords', ['Admin\\UserController', 'resetAllPasswords'], [CsrfMiddleware::class]);
    $router->get('/users/{id}/edit', ['Admin\\UserController', 'edit']);
    $router->post('/users/{id}', ['Admin\\UserController', 'update'], [CsrfMiddleware::class]);
    $router->post('/users/{id}/toggle-active', ['Admin\\UserController', 'toggleActive'], [CsrfMiddleware::class]);
    $router->post('/users/{id}/delete', ['Admin\\UserController', 'destroy'], [CsrfMiddleware::class]);

    // Audit
    $router->get('/audit', ['Admin\\AuditController', 'index']);

    // Settings
    $router->get('/settings', ['Admin\\SettingsController', 'index']);
    $router->post('/settings', ['Admin\\SettingsController', 'update'], [CsrfMiddleware::class]);

    $router->get('/chat/settings', ['Admin\\ChatSettingsController', 'index']);
    $router->post('/chat/settings', ['Admin\\ChatSettingsController', 'save'], [CsrfMiddleware::class]);
    $router->post('/chat/settings/rebuild', ['Admin\\ChatSettingsController', 'rebuildChannels'], [CsrfMiddleware::class]);

    // Announcements
    $router->get('/announcements', ['Admin\\AnnouncementController', 'index']);
    $router->get('/announcements/create', ['Admin\\AnnouncementController', 'create']);
    $router->post('/announcements', ['Admin\\AnnouncementController', 'store'], [CsrfMiddleware::class]);
    $router->get('/announcements/{id}/edit', ['Admin\\AnnouncementController', 'edit']);
    $router->post('/announcements/{id}', ['Admin\\AnnouncementController', 'update'], [CsrfMiddleware::class]);
    $router->post('/announcements/{id}/delete', ['Admin\\AnnouncementController', 'destroy'], [CsrfMiddleware::class]);

    // Assets
    $router->get('/assets', ['Admin\\AssetController', 'index']);
    $router->get('/assets/create', ['Admin\\AssetController', 'create']);
    $router->post('/assets', ['Admin\\AssetController', 'store'], [CsrfMiddleware::class]);
    $router->post('/assets/{id}', ['Admin\\AssetController', 'update'], [CsrfMiddleware::class]);
    $router->post('/assets/{id}/delete', ['Admin\\AssetController', 'destroy'], [CsrfMiddleware::class]);

    // Loans & Advances
    $router->get('/loans', ['Admin\\LoanController', 'index']);
    $router->post('/loans', ['Admin\\LoanController', 'store'], [CsrfMiddleware::class]);
    $router->post('/loans/{id}/approve', ['Admin\\LoanController', 'approve'], [CsrfMiddleware::class]);
    $router->get('/advances', ['Admin\\AdvanceController', 'index']);
    $router->post('/advances', ['Admin\\AdvanceController', 'store'], [CsrfMiddleware::class]);
    $router->post('/advances/{id}/approve', ['Admin\\AdvanceController', 'approve'], [CsrfMiddleware::class]);

    // Overtime
    $router->get('/overtime', ['Admin\\OvertimeController', 'index']);
    $router->post('/overtime/{id}/approve', ['Admin\\OvertimeController', 'approve'], [CsrfMiddleware::class]);
    $router->post('/overtime/{id}/reject', ['Admin\\OvertimeController', 'reject'], [CsrfMiddleware::class]);
    $router->post('/overtime/{id}/delete', ['Admin\\OvertimeController', 'destroy'], [CsrfMiddleware::class]);

    // Payslips
    $router->get('/payslips', ['Admin\\PayslipController', 'index']);
    $router->get('/payslips/{id}/edit', ['Admin\\PayslipController', 'edit']);
    $router->post('/payslips/{id}/update', ['Admin\\PayslipController', 'update'], [CsrfMiddleware::class]);
    $router->post('/payslips/{id}/delete', ['Admin\\PayslipController', 'destroy'], [CsrfMiddleware::class]);
    $router->get('/payslips/{id}', ['Admin\\PayslipController', 'show']);

    // Work Activity Monitoring
    $router->get('/monitoring', ['Admin\\MonitoringController', 'index']);
    $router->get('/monitoring/live', ['Admin\\MonitoringController', 'live']);
    $router->get('/monitoring/activity', ['Admin\\MonitoringController', 'activity']);
    $router->get('/monitoring/web-activity', ['Admin\\MonitoringController', 'webActivity']);
    $router->get('/monitoring/site-rules', ['Admin\\MonitoringController', 'siteRules']);
    $router->post('/monitoring/site-rules', ['Admin\\MonitoringController', 'storeSiteRule'], [CsrfMiddleware::class]);
    $router->post('/monitoring/site-rules/schedules', ['Admin\\MonitoringController', 'storeSchedule'], [CsrfMiddleware::class]);
    $router->post('/monitoring/site-rules/schedules/assign', ['Admin\\MonitoringController', 'assignSchedule'], [CsrfMiddleware::class]);
    $router->post('/monitoring/site-rules/exceptions/{id}/review', ['Admin\\MonitoringController', 'reviewException'], [CsrfMiddleware::class]);
    $router->get('/monitoring/applications', ['Admin\\MonitoringController', 'applications']);
    $router->get('/monitoring/screenshots', ['Admin\\MonitoringController', 'screenshots']);
    $router->post('/monitoring/screenshots/{id}/delete', ['Admin\\MonitoringController', 'deleteScreenshot'], [CsrfMiddleware::class]);
    $router->get('/monitoring/devices', ['Admin\\MonitoringController', 'devices']);
    $router->post('/monitoring/devices/{id}/approve', ['Admin\\MonitoringController', 'approveDevice'], [CsrfMiddleware::class]);
    $router->post('/monitoring/devices/{id}/revoke', ['Admin\\MonitoringController', 'revokeDevice'], [CsrfMiddleware::class]);
    $router->get('/monitoring/policies', ['Admin\\MonitoringController', 'policies']);
    $router->post('/monitoring/policies/assign', ['Admin\\MonitoringController', 'assignPolicy'], [CsrfMiddleware::class]);
    $router->post('/monitoring/policies', ['Admin\\MonitoringController', 'storePolicy'], [CsrfMiddleware::class]);
    $router->post('/monitoring/policies/{id}', ['Admin\\MonitoringController', 'updatePolicy'], [CsrfMiddleware::class]);
    $router->get('/monitoring/alerts', ['Admin\\MonitoringController', 'alerts']);
    $router->post('/monitoring/alerts/{id}/resolve', ['Admin\\MonitoringController', 'resolveAlert'], [CsrfMiddleware::class]);
    $router->get('/monitoring/reports', ['Admin\\MonitoringController', 'reports']);
    $router->get('/monitoring/audit', ['Admin\\MonitoringController', 'audit']);
    $router->get('/monitoring/disputes', ['Admin\\MonitoringController', 'disputes']);
    $router->post('/monitoring/disputes/{id}/review', ['Admin\\MonitoringController', 'reviewDispute'], [CsrfMiddleware::class]);
    $router->post('/monitoring/sessions/{id}/stop', ['Admin\\MonitoringController', 'stopSession'], [CsrfMiddleware::class]);

    // Documents
    $router->get('/documents', ['Admin\\DocumentController', 'index']);
    $router->post('/documents', ['Admin\\DocumentController', 'store'], [CsrfMiddleware::class]);
    $router->post('/documents/{id}/delete', ['Admin\\DocumentController', 'destroy'], [CsrfMiddleware::class]);

    // Notifications list
    $router->get('/notifications', ['Admin\\NotificationController', 'index']);

    // Biometric devices & QR codes
    $router->get('/attendance/devices', ['Admin\\BiometricDeviceController', 'index']);
    $router->get('/attendance/devices/create', ['Admin\\BiometricDeviceController', 'create']);
    $router->post('/attendance/devices', ['Admin\\BiometricDeviceController', 'store'], [CsrfMiddleware::class]);
    $router->get('/attendance/devices/unmatched', ['Admin\\BiometricDeviceController', 'unmatchedLogs']);
    $router->post('/attendance/devices/assign-pin', ['Admin\\BiometricDeviceController', 'assignPin'], [CsrfMiddleware::class]);
    $router->get('/attendance/devices/{id}/edit', ['Admin\\BiometricDeviceController', 'edit']);
    $router->post('/attendance/devices/{id}/update', ['Admin\\BiometricDeviceController', 'update'], [CsrfMiddleware::class]);
    $router->post('/attendance/devices/{id}/delete', ['Admin\\BiometricDeviceController', 'destroy'], [CsrfMiddleware::class]);
    $router->get('/attendance/devices/{id}/logs', ['Admin\\BiometricDeviceController', 'logs']);
    $router->get('/attendance/qr-codes', ['Admin\\BiometricDeviceController', 'qrCodes']);
    $router->post('/attendance/qr-codes/generate', ['Admin\\BiometricDeviceController', 'generateQr'], [CsrfMiddleware::class]);
    $router->post('/attendance/qr-codes/{id}/delete', ['Admin\\BiometricDeviceController', 'deleteQr'], [CsrfMiddleware::class]);
});

// Employee self-service
$router->group(['prefix' => '/employee', 'middleware' => [AuthMiddleware::class, EmployeeMiddleware::class]], function ($router) {
    $router->get('/onboarding/agreement', ['Employee\\EmploymentAgreementController', 'show']);
    $router->get('/onboarding/agreement/template', ['Employee\\EmploymentAgreementController', 'template']);
    $router->post('/onboarding/agreement/accept', ['Employee\\EmploymentAgreementController', 'accept'], [CsrfMiddleware::class]);

    $router->get('/dashboard', ['Employee\\DashboardController', 'index'])->name('employee.dashboard');

    $router->get('/attendance', ['Employee\\AttendanceController', 'index']);
    $router->get('/attendance/calendar', ['Employee\\AttendanceController', 'calendar']);
    $router->get('/attendance/corrections', ['Employee\\AttendanceController', 'corrections']);

    $router->get('/leave', ['Employee\\LeaveController', 'index']);
    $router->get('/leave/create', ['Employee\\LeaveController', 'create']);
    $router->post('/leave', ['Employee\\LeaveController', 'store'], [CsrfMiddleware::class]);
    $router->get('/leave/{id}', ['Employee\\LeaveController', 'show']);
    $router->post('/leave/{id}/cancel', ['Employee\\LeaveController', 'cancel'], [CsrfMiddleware::class]);

    $router->get('/payslips', ['Employee\\PayslipController', 'index']);
    $router->get('/payslips/{id}', ['Employee\\PayslipController', 'show']);
    $router->get('/payslips/{id}/download', ['Employee\\PayslipController', 'download']);

    // Employees can review their own domain activity and submit explanations.
    $router->get('/monitoring', ['Employee\\MonitoringController', 'forbidden']);
    $router->get('/monitoring/acknowledge', ['Employee\\MonitoringController', 'acknowledge']);
    $router->post('/monitoring/acknowledge', ['Employee\\MonitoringController', 'acknowledgePost'], [CsrfMiddleware::class]);
    $router->get('/monitoring/activity', ['Employee\\MonitoringController', 'activity']);
    $router->post('/monitoring/activity/disputes', ['Employee\\MonitoringController', 'submitDispute'], [CsrfMiddleware::class]);
    $router->post('/monitoring/activity/exceptions', ['Employee\\MonitoringController', 'requestException'], [CsrfMiddleware::class]);
    $router->get('/monitoring/screenshots', ['Employee\\MonitoringController', 'forbidden']);
    $router->get('/monitoring/devices', ['Employee\\MonitoringController', 'forbidden']);
    $router->get('/monitoring/policy', ['Employee\\MonitoringController', 'forbidden']);
    $router->get('/monitoring/acknowledgements', ['Employee\\MonitoringController', 'forbidden']);

    $router->get('/profile', ['Employee\\ProfileController', 'index']);
    $router->post('/profile', ['Employee\\ProfileController', 'update'], [CsrfMiddleware::class]);

    $router->get('/documents', ['Employee\\DocumentController', 'index']);
    $router->get('/assets', ['Employee\\AssetController', 'index']);
    $router->get('/loans', ['Employee\\LoanController', 'index']);
    $router->get('/loans/create', ['Employee\\LoanController', 'create']);
    $router->post('/loans', ['Employee\\LoanController', 'store'], [CsrfMiddleware::class]);
    $router->get('/advances', ['Employee\\AdvanceController', 'index']);
    $router->get('/advances/create', ['Employee\\AdvanceController', 'create']);
    $router->post('/advances', ['Employee\\AdvanceController', 'store'], [CsrfMiddleware::class]);
    $router->get('/notifications', ['Employee\\NotificationController', 'index']);

    // QR punch
    $router->get('/qr-punch', ['Employee\\QrPunchController', 'show']);
    $router->post('/qr-punch', ['Employee\\QrPunchController', 'punch'], [CsrfMiddleware::class]);
});
