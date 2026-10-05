<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Middleware\CsrfMiddleware;
use App\Services\ObservabilityService;
use Throwable;

class Application
{
    private static ?Application $instance = null;
    private Router $router;
    private Request $request;
    private Response $response;
    private array $config = [];

    public function __construct()
    {
        self::$instance = $this;
        $this->loadEnvironment();
        $this->config['app'] = require dirname(__DIR__, 2) . '/config/app.php';
        $this->config['database'] = require dirname(__DIR__, 2) . '/config/database.php';
        $this->config['mail'] = require dirname(__DIR__, 2) . '/config/mail.php';
        $this->config['permissions'] = require dirname(__DIR__, 2) . '/config/permissions.php';
        $this->config['navigation'] = require dirname(__DIR__, 2) . '/config/navigation.php';

        date_default_timezone_set($this->config['app']['timezone']);

        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);

        Session::start($this->config['app']);
        SecurityHeaders::apply($this->config['app']);
    }

    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function run(): void
    {
        ObservabilityService::startRequest();
        register_shutdown_function(static fn () => ObservabilityService::finishRequest());
        try {
            $this->checkInstallation();
            $webRoutes = dirname(__DIR__, 2) . '/routes/web.php';
            $apiRoutes = dirname(__DIR__, 2) . '/routes/api.php';

            if (file_exists($webRoutes)) {
                require $webRoutes;
            }
            if (file_exists($apiRoutes)) {
                require $apiRoutes;
            }

            $this->router->dispatch();
        } catch (HttpException $e) {
            $this->handleHttpException($e);
        } catch (Throwable $e) {
            ObservabilityService::exception($e);
            $this->handleException($e);
        }
    }

    private function loadEnvironment(): void
    {
        $root = dirname(__DIR__, 2);
        if (file_exists($root . '/.env') && class_exists(\Dotenv\Dotenv::class)) {
            $dotenv = \Dotenv\Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        } elseif (file_exists($root . '/.env')) {
            $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $_ENV[$name] = $value;
                putenv("{$name}={$value}");
            }
        }
    }

    private function checkInstallation(): void
    {
        $lockFile = dirname(__DIR__, 2) . '/install/installed.lock';
        $uri = $this->request->uri();

        if (!file_exists($lockFile) && !str_starts_with($uri, '/install')) {
            if ($this->request->isApi()) {
                $this->response->json(['success' => false, 'message' => 'Application not installed.'], 503);
                return;
            }
            header('Location: /install/');
            exit;
        }
    }

    private function handleHttpException(HttpException $e): void
    {
        if ($this->request->isApi() || $this->request->wantsJson()) {
            $this->response->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ], $e->getStatusCode());
            return;
        }

        $code = $e->getStatusCode();
        $view = "errors/{$code}";
        if (!file_exists($this->config['app']['paths']['views'] . "/{$view}.php")) {
            $view = 'errors/error';
        }

        $this->response->status($code)->view($view, [
            'code' => $code,
            'message' => $e->getMessage(),
        ]);
    }

    private function handleException(Throwable $e): void
    {
        $logger = new Logger();
        $logger->error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $debug = (bool) $this->config('app.debug', false);
        $message = $debug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.';

        if ($this->request->isApi() || $this->request->wantsJson()) {
            $payload = ['success' => false, 'message' => $message];
            if ($debug) {
                $payload['debug'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ];
            }
            $this->response->json($payload, 500);
            return;
        }

        $this->response->status(500)->view('errors/error', [
            'code' => 500,
            'message' => $message,
            'exception' => $debug ? $e : null,
        ]);
    }
}
