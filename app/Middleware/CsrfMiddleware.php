<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use Closure;

class CsrfMiddleware
{
    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_csrf')
                ?? $request->header('X-CSRF-TOKEN')
                ?? $request->header('X-Xsrf-Token');

            if (!Session::verifyCsrf($token)) {
                if ($request->wantsJson()) {
                    $response->json(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
                }
                throw new HttpException('Invalid CSRF token.', 419);
            }
        }

        return $next();
    }
}
