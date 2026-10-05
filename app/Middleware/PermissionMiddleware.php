<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuthService;
use Closure;

class PermissionMiddleware
{
    public function __construct(private readonly ?string $permission = null)
    {
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $auth = new AuthService();

        if (!$auth->check()) {
            if ($request->wantsJson()) {
                $response->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            $response->redirect('/login');
        }

        if ($this->permission && !$auth->can($this->permission)) {
            throw new HttpException('You do not have permission to access this resource.', 403);
        }

        return $next();
    }
}
