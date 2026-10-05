<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use Closure;

class GuestMiddleware
{
    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $auth = new AuthService();

        if ($auth->check()) {
            $user = $auth->user();
            $role = $user['primary_role'] ?? 'employee';
            if (in_array($role, ['super_admin', 'company_admin', 'hr_manager', 'department_manager', 'accountant'], true)) {
                $response->redirect('/admin/dashboard');
            }
            if (!$auth->employee()) {
                $auth->logout();
                Session::flash('error', 'Your account is not linked to an employee profile. Please contact your administrator.');
                $response->redirect('/login');
            }
            $response->redirect('/employee/dashboard');
        }

        return $next();
    }
}
