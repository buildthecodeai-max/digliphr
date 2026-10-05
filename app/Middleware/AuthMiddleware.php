<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use Closure;

class AuthMiddleware
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

        return $next();
    }
}
