<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuthService;
use Closure;

class AdminMiddleware
{
    private array $adminRoles = [
        'super_admin',
        'company_admin',
        'hr_manager',
        'department_manager',
        'accountant',
    ];

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $auth = new AuthService();

        if (!$auth->check()) {
            if ($request->wantsJson()) {
                $response->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            $response->redirect('/login');
        }

        $role = $auth->user()['primary_role'] ?? 'employee';
        if (!in_array($role, $this->adminRoles, true)) {
            throw new HttpException('Access denied.', 403);
        }

        return $next();
    }
}
