<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use Closure;

class EmployeeMiddleware
{
    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $auth = new AuthService();

        if (!$auth->check()) {
            if ($request->wantsJson()) {
                $response->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            $response->redirect('/login');
        }

        if (!$auth->employee()) {
            if ($auth->isAdmin()) {
                $response->redirect('/admin/dashboard');
            }

            // Accounts that are not linked to an employee cannot use this
            // portal. Clear the invalid session so / and /login cannot send
            // the browser straight back into a repeating 403 response.
            $auth->logout();
            Session::flash('error', 'Your account is not linked to an employee profile. Please contact your administrator.');
            $response->redirect('/login');
        }

        $employee = $auth->employee();
        $uri = $request->uri();
        if (!str_starts_with($uri, '/employee/onboarding/agreement') && !str_starts_with($uri, '/employee/monitoring/acknowledge')) {
            $agreement = new \App\Services\EmploymentAgreementService();
            if ($agreement->installed() && $agreement->pendingForEmployee((int) $employee['id'], (int) $auth->id())) {
                $response->redirect('/employee/onboarding/agreement');
            }
        }

        return $next();
    }
}
