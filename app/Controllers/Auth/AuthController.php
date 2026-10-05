<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Session;
use App\Exceptions\HttpException;
use PHPMailer\PHPMailer\PHPMailer;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $brand = (new \App\Services\BrandingService())->publicBrand((string) $this->request->input('company'));
        $this->view('auth/login', [
            'title' => 'Sign In',
            'brand' => $brand,
        ], null);
    }

    public function login(): void
    {
        try {
            $data = $this->validate([
                'email' => 'required|email',
                'password' => 'required|min:6',
            ]);
        } catch (HttpException) {
            $this->redirect('/login');
            return;
        }

        $remember = (bool) $this->request->input('remember');
        $result = $this->auth->attempt($data['email'], $data['password'], $remember);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            Session::flash('old', ['email' => $data['email']]);
            $company = trim((string) $this->request->input('company'));
            $this->redirect('/login' . ($company !== '' ? '?company=' . urlencode($company) : ''));
        }

        Session::flash('success', 'Welcome back!');
        $role = $this->auth->user()['primary_role'] ?? 'employee';
        if (in_array($role, ['super_admin', 'company_admin', 'hr_manager', 'department_manager', 'accountant'], true)) {
            $this->redirect('/admin/dashboard');
        }
        $employee = $this->auth->employee();
        if (!$employee) {
            // An authenticated account without an administrative role or an
            // employee profile has no valid application area. End the session
            // here so it cannot become trapped between /login and a 403 page.
            $this->auth->logout();
            Session::flash('error', 'Your account is not linked to an employee profile. Please contact your administrator.');
            $this->redirect('/login');
        }

        $agreements = new \App\Services\EmploymentAgreementService();
        if ($agreements->installed() && $agreements->pendingForEmployee((int) $employee['id'], (int) $this->auth->id())) {
            $this->redirect('/employee/onboarding/agreement');
        }
        $this->redirect('/employee/dashboard');
    }

    public function logout(): void
    {
        $this->auth->logout();
        Session::flash('success', 'You have been signed out.');
        $this->redirect('/login');
    }

    public function showForgotPassword(): void
    {
        $this->view('auth/forgot-password', ['title' => 'Forgot Password'], null);
    }

    public function forgotPassword(): void
    {
        try {
            $data = $this->validate(['email' => 'required|email']);
        } catch (HttpException) {
            $this->redirect('/forgot-password');
            return;
        }

        $token = $this->auth->createPasswordReset($data['email']);
        if ($token) {
            $this->sendResetEmail($data['email'], $token);
        }

        Session::flash('success', 'If that email exists, a reset link has been sent.');
        $this->redirect('/login');
    }

    public function showResetPassword(string $token): void
    {
        $this->view('auth/reset-password', [
            'title' => 'Reset Password',
            'token' => $token,
            'email' => $this->request->input('email', ''),
        ], null);
    }

    public function resetPassword(): void
    {
        try {
            $data = $this->validate([
                'email' => 'required|email',
                'token' => 'required',
                'password' => 'required|min:8|confirmed',
            ]);
        } catch (HttpException) {
            $this->redirect('/forgot-password');
            return;
        }

        if (!$this->auth->resetPassword($data['email'], $data['token'], $data['password'])) {
            Session::flash('error', 'Invalid or expired reset link.');
            $this->redirect('/forgot-password');
        }

        Session::flash('success', 'Password updated. Please sign in.');
        $this->redirect('/login');
    }

    public function showChangePassword(): void
    {
        $layout = $this->auth->isAdmin() ? 'layouts/admin' : 'layouts/employee';
        $this->view('auth/change-password', ['title' => 'Change Password'], $layout);
    }

    public function changePassword(): void
    {
        try {
            $data = $this->validate([
                'current_password' => 'required',
                'password' => 'required|min:8|confirmed',
            ]);
        } catch (HttpException) {
            $this->redirect('/change-password');
            return;
        }

        $userId = $this->auth->id();
        if (!$userId) {
            $this->redirect('/login');
        }

        $result = $this->auth->changePassword($userId, $data['current_password'], $data['password']);
        if (!$result['success']) {
            Session::flash('error', $result['message']);
            $this->redirect('/change-password');
        }

        Session::flash('success', $result['message']);
        $this->redirect('/login');
    }

    private function sendResetEmail(string $email, string $token): void
    {
        $resetUrl = url('/reset-password/' . $token . '?email=' . urlencode($email));
        $mailConfig = config('mail');

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'] ?? 'localhost';
            $mail->SMTPAuth = !empty($mailConfig['username']);
            $mail->Username = $mailConfig['username'] ?? '';
            $mail->Password = $mailConfig['password'] ?? '';
            $mail->SMTPSecure = $mailConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($mailConfig['port'] ?? 587);
            $mail->setFrom($mailConfig['from_address'] ?? 'noreply@example.com', $mailConfig['from_name'] ?? config('app.name'));
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset - ' . config('app.name');
            $mail->Body = '<p>Click the link below to reset your password:</p><p><a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p><p>This link expires in 1 hour.</p>';
            $mail->send();
        } catch (\Throwable) {
            // Log silently; user still sees generic success message
        }
    }
}
