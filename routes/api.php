<?php

declare(strict_types=1);

use App\Core\Application;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\EmployeeMiddleware;

$app = Application::getInstance();
$router = $app->router();

$router->group(['prefix' => '/api'], function ($router) use ($app) {
    $router->get('/health', function () use ($app) {
        $app->response()->success('OK', ['time' => date('c')]);
    });

    $router->group(['middleware' => [AuthMiddleware::class]], function ($router) use ($app) {
        $router->get('/me', function () use ($app) {
            $auth = new \App\Services\AuthService();
            $app->response()->success('Profile loaded.', [
                'user' => $auth->user(),
                'employee' => $auth->employee(),
                'permissions' => $auth->permissions(),
            ]);
        });

        $router->get('/notifications', function () use ($app) {
            $auth = new \App\Services\AuthService();
            $userId = $auth->id();
            if (!$userId) {
                $app->response()->error('Unauthenticated.', null, 401);
            }
            $rows = \App\Core\Database::getInstance()->fetchAll(
                'SELECT id, type, title, message, action_url, is_read, created_at
                 FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50',
                ['uid' => $userId]
            );
            $unread = 0;
            foreach ($rows as $row) {
                if (!(int) ($row['is_read'] ?? 0)) {
                    $unread++;
                }
            }
            $app->response()->success('Notifications loaded.', [
                'items' => $rows,
                'unread_count' => $unread,
            ]);
        });

        $router->post('/notifications/{id}/read', function ($request, $response, $id) {
            \App\Core\Database::getInstance()->update('notifications', [
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s'),
            ], 'id = :id AND user_id = :uid', [
                'id' => $id,
                'uid' => (new \App\Services\AuthService())->id(),
            ]);
            $response->success('Notification marked as read.');
        }, [CsrfMiddleware::class]);

        $router->get('/dashboard/stats', ['Admin\\DashboardController', 'stats']);

        $router->get('/employees/search', function ($request, $response) {
            $auth = new \App\Services\AuthService();
            if (!$auth->can('employees.view')) {
                $response->error('Forbidden.', null, 403);
            }
            $q = trim((string) $request->query('q', ''));
            if (mb_strlen($q) < 2) {
                $response->success('Search query is too short.', ['items' => []]);
            }
            $db = \App\Core\Database::getInstance();
            $tenant = new \App\Services\TenantContext($auth);
            $scope = $tenant->sql('e.company_id', 'api_employee_company');
            $like = '%' . $q . '%';
            $items = $db->fetchAll(
                'SELECT e.id, e.employee_code, e.first_name, e.last_name, e.company_email,
                        e.employment_status, d.name AS department_name
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE e.deleted_at IS NULL AND (' . $scope['sql'] . ')
                   AND (e.first_name LIKE :q OR e.last_name LIKE :q OR e.employee_code LIKE :q OR e.company_email LIKE :q)
                 ORDER BY e.first_name, e.last_name LIMIT 8',
                array_merge(['q' => $like], $scope['params'])
            );
            $response->success('Employees found.', ['items' => $items]);
        });

        $router->get('/reports/summary', ['Api\\ReportApiController', 'summary']);
        $router->get('/reports/attendance', ['Api\\ReportApiController', 'attendance']);
        $router->get('/reports/leave', ['Api\\ReportApiController', 'leave']);
        $router->get('/reports/payroll', ['Api\\ReportApiController', 'payroll']);
        $router->get('/reports/loans', ['Api\\ReportApiController', 'loans']);
        $router->get('/reports/workforce', ['Api\\ReportApiController', 'workforce']);
        $router->get('/reports/documents', ['Api\\ReportApiController', 'documents']);

        // Team Chat API
        $router->get('/chat/status', ['Api\\ChatApiController', 'status']);
        $router->get('/chat/bootstrap', ['Api\\ChatApiController', 'bootstrap']);
        $router->get('/chat/poll', ['Api\\ChatApiController', 'poll']);
        $router->get('/chat/messages', ['Api\\ChatApiController', 'messages']);
        $router->post('/chat/messages', ['Api\\ChatApiController', 'send'], [CsrfMiddleware::class]);
        $router->post('/chat/messages/{id}/edit', ['Api\\ChatApiController', 'editMessage'], [CsrfMiddleware::class]);
        $router->post('/chat/messages/{id}/delete', ['Api\\ChatApiController', 'deleteMessage'], [CsrfMiddleware::class]);
        $router->post('/chat/messages/{id}/react', ['Api\\ChatApiController', 'react'], [CsrfMiddleware::class]);
        $router->post('/chat/messages/{id}/pin', ['Api\\ChatApiController', 'pin'], [CsrfMiddleware::class]);
        $router->post('/chat/messages/{id}/save', ['Api\\ChatApiController', 'saveMessage'], [CsrfMiddleware::class]);
        $router->post('/chat/messages/{id}/acknowledge', ['Api\\ChatApiController', 'acknowledge'], [CsrfMiddleware::class]);
        $router->post('/chat/channels', ['Api\\ChatApiController', 'createChannel'], [CsrfMiddleware::class]);
        $router->post('/chat/channels/{id}/join', ['Api\\ChatApiController', 'joinChannel'], [CsrfMiddleware::class]);
        $router->post('/chat/channels/{id}/invite', ['Api\\ChatApiController', 'inviteMembers'], [CsrfMiddleware::class]);
        $router->post('/chat/channels/{id}/members/{userId}/remove', ['Api\\ChatApiController', 'removeMember'], [CsrfMiddleware::class]);
        $router->get('/chat/channels/{id}/members', ['Api\\ChatApiController', 'members']);
        $router->post('/chat/dm', ['Api\\ChatApiController', 'startDm'], [CsrfMiddleware::class]);
        $router->get('/chat/users', ['Api\\ChatApiController', 'users']);
        $router->get('/chat/members', ['Api\\ChatApiController', 'membersDirectory']);
        $router->get('/chat/search', ['Api\\ChatApiController', 'search']);
        $router->post('/chat/typing', ['Api\\ChatApiController', 'typing'], [CsrfMiddleware::class]);
    });

    $router->group(['middleware' => [AuthMiddleware::class, EmployeeMiddleware::class]], function ($router) {
        $router->get('/attendance/today', ['Api\\AttendanceApiController', 'todayStatus']);
        $router->post('/attendance/check-in', ['Api\\AttendanceApiController', 'checkIn'], [CsrfMiddleware::class]);
        $router->post('/attendance/check-out', ['Api\\AttendanceApiController', 'checkOut'], [CsrfMiddleware::class]);
        $router->post('/attendance/corrections', ['Api\\AttendanceApiController', 'requestCorrection'], [CsrfMiddleware::class]);
    });

    // Desktop monitoring agent — Bearer token auth (not cookie-only)
    $router->post('/monitoring/auth/login', ['Api\\MonitoringApiController', 'login']);

    $router->group(['middleware' => [\App\Middleware\MonitoringAgentMiddleware::class]], function ($router) {
        $router->post('/monitoring/devices/register', ['Api\\MonitoringApiController', 'registerDevice']);
        $router->get('/monitoring/policy', ['Api\\MonitoringApiController', 'policy']);
        $router->post('/monitoring/policy/acknowledge', ['Api\\MonitoringApiController', 'acknowledge']);
        $router->post('/monitoring/sessions/start', ['Api\\MonitoringApiController', 'startSession']);
        $router->post('/monitoring/sessions/stop', ['Api\\MonitoringApiController', 'stopSession']);
        $router->post('/monitoring/heartbeat', ['Api\\MonitoringApiController', 'heartbeat']);
        $router->post('/monitoring/activity/batch', ['Api\\MonitoringApiController', 'activityBatch']);
        $router->post('/monitoring/screenshots/authorize', ['Api\\MonitoringApiController', 'authorizeScreenshot']);
        $router->post('/monitoring/screenshots/upload', ['Api\\MonitoringApiController', 'uploadScreenshot']);
        $router->post('/monitoring/screenshots/confirm', ['Api\\MonitoringApiController', 'confirmScreenshot']);
    });
});
