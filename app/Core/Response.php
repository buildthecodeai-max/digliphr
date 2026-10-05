<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function success(string $message = 'Success', mixed $data = null, int $status = 200): void
    {
        $payload = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        $this->json($payload, $status);
    }

    public function error(string $message = 'Error', mixed $errors = null, int $status = 400, ?string $code = null): void
    {
        $payload = ['success' => false, 'message' => $message];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        $this->json($payload, $status);
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->statusCode = $status;
        $this->headers['Location'] = $url;
        $this->sendHeaders();
        exit;
    }

    public function view(string $view, array $data = [], ?string $layout = null): void
    {
        $content = View::render($view, $data, $layout);
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->sendHeaders();
        echo $content;
        exit;
    }

    public function download(string $path, ?string $filename = null, string $mime = 'application/octet-stream'): void
    {
        if (!file_exists($path)) {
            $this->status(404)->json(['success' => false, 'message' => 'File not found.']);
        }

        $filename = $filename ?? basename($path);
        $this->headers['Content-Type'] = $mime;
        $this->headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        $this->headers['Content-Length'] = (string) filesize($path);
        $this->sendHeaders();
        readfile($path);
        exit;
    }

    public function file(string $path, string $mime = 'application/octet-stream'): void
    {
        if (!file_exists($path)) {
            http_response_code(404);
            exit('File not found.');
        }

        $this->headers['Content-Type'] = $mime;
        $this->headers['Content-Length'] = (string) filesize($path);
        $this->headers['Cache-Control'] = 'private, max-age=3600';
        $this->sendHeaders();
        readfile($path);
        exit;
    }

    public function send(mixed $content): void
    {
        if (is_array($content)) {
            $this->json($content);
            return;
        }

        $this->sendHeaders();
        echo (string) $content;
        exit;
    }

    private function sendHeaders(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
    }
}
