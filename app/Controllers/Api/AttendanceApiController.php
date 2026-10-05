<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AttendanceService;
use App\Services\AttendanceSecurityService;

class AttendanceApiController extends Controller
{
    private AttendanceService $attendanceService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->attendanceService = new AttendanceService();
    }

    public function checkIn(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->jsonError('Employee profile required.', null, 403);
        }

        $result = $this->attendanceService->checkIn(
            (int) $employee['id'],
            $this->request->all(),
            $this->user()['id'] ?? null
        );

        if (!empty($result['device_cookie_token'])) {
            $this->setAttendanceDeviceCookie((string) $result['device_cookie_token']);
        }
        unset($result['device_cookie_token']);

        if (!$result['success']) {
            $code = $result['code'] ?? null;
            $status = $code === 'ATTENDANCE_ALREADY_ACTIVE' ? 409
                : (in_array($code, ['ATTENDANCE_DEVICE_NOT_AUTHORIZED', 'ATTENDANCE_DEVICE_PENDING', 'ATTENDANCE_IP_NOT_ALLOWED'], true) ? 403 : 422);
            $this->jsonError($result['message'], $result['errors'] ?? null, $status, $code);
        }

        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    private function setAttendanceDeviceCookie(string $token): void
    {
        setcookie(AttendanceSecurityService::COOKIE, $token, [
            'expires' => time() + (86400 * 365 * 5),
            'path' => '/',
            'secure' => (bool) config('app.session_secure', false),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function checkOut(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->jsonError('Employee profile required.', null, 403);
        }

        $result = $this->attendanceService->checkOut(
            (int) $employee['id'],
            $this->request->all(),
            $this->user()['id'] ?? null
        );

        if (!$result['success']) {
            $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function todayStatus(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->jsonError('Employee profile required.', null, 403);
        }

        $result = $this->attendanceService->todayStatus((int) $employee['id']);

        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }

        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function requestCorrection(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->jsonError('Employee profile required.', null, 403);
        }

        $result = $this->attendanceService->requestCorrection(
            (int) $employee['id'],
            $this->request->all(),
            $this->user()['id'] ?? null
        );

        if (!$result['success']) {
            $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }
}
