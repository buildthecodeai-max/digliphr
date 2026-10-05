<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Services\AuthService;
use App\Services\TenantContext;

abstract class Controller
{
    protected AuthService $auth;
    protected TenantContext $tenant;

    public function __construct(
        protected Request $request,
        protected Response $response
    ) {
        $this->auth = new AuthService();
        $this->tenant = new TenantContext($this->auth);
    }

    protected function view(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        $data['authUser'] = $this->auth->user();
        $data['authEmployee'] = $this->auth->employee();
        $data['flash_success'] = Session::flash('success');
        $data['flash_error'] = Session::flash('error');
        $data['flash_warning'] = Session::flash('warning');
        $data['appName'] = config('app.name');
        $data['csrf'] = Session::csrfToken();

        $this->response->view($view, $data, $layout);
    }

    protected function jsonSuccess(string $message = 'Success', mixed $data = null, int $status = 200): void
    {
        $this->response->success($message, $data, $status);
    }

    protected function jsonError(string $message = 'Error', mixed $errors = null, int $status = 400, ?string $code = null): void
    {
        $this->response->error($message, $errors, $status, $code);
    }

    protected function redirect(string $url): void
    {
        $this->response->redirect($url);
    }

    protected function back(): void
    {
        $referer = $this->request->header('Referer', '/');
        $this->response->redirect($referer);
    }

    protected function validate(array $rules, ?array $data = null): array
    {
        $validator = new Validator($data ?? $this->request->all(), $rules);
        if (!$validator->passes()) {
            if ($this->request->wantsJson()) {
                $this->jsonError('Validation failed.', $validator->errors(), 422);
            }
            Session::flash('errors', $validator->errors());
            Session::flash('old', $this->request->all());
            // Redirect back so field errors render on the form (not a bare 422 page).
            $this->back();
        }
        return $validator->validated();
    }

    protected function authorize(string $permission): void
    {
        if (!$this->auth->can($permission)) {
            throw new HttpException('You do not have permission to perform this action.', 403);
        }
    }

    protected function user(): ?array
    {
        return $this->auth->user();
    }

    protected function employee(): ?array
    {
        return $this->auth->employee();
    }
}
