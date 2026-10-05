<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\BrandingService;
use RuntimeException;

class SsoController extends Controller
{
    public function redirect(): void
    {
        $brand = (new BrandingService())->publicBrand((string) $this->request->input('company'));
        if (empty($brand['company']) || !$brand['sso_enabled']) {
            flash('error', 'Single sign-on is not enabled for this company.');
            $this->redirect('/login');
        }
        $settings = $brand['settings'];
        $state = bin2hex(random_bytes(24));
        Session::set('oidc_state', $state);
        Session::set('oidc_company_id', (int) $brand['company']['id']);
        $params = http_build_query([
            'client_id' => $settings['sso_client_id'],
            'redirect_uri' => url('/auth/sso/callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ]);
        $separator = str_contains($settings['sso_authorize_url'], '?') ? '&' : '?';
        $this->redirect($settings['sso_authorize_url'] . $separator . $params);
    }

    public function callback(): void
    {
        try {
            $state = (string) $this->request->input('state');
            $expected = (string) Session::pull('oidc_state', '');
            $companyId = (int) Session::pull('oidc_company_id', 0);
            if ($state === '' || $expected === '' || !hash_equals($expected, $state) || $companyId < 1) throw new RuntimeException('The SSO session expired. Please try again.');
            if ($this->request->input('error')) throw new RuntimeException('The identity provider declined sign-in.');
            $code = (string) $this->request->input('code');
            if ($code === '') throw new RuntimeException('The identity provider did not return an authorization code.');
            $settings = (new BrandingService())->settings($companyId);
            $token = $this->postForm((string) ($settings['sso_token_url'] ?? ''), [
                'grant_type' => 'authorization_code', 'code' => $code,
                'redirect_uri' => url('/auth/sso/callback'),
                'client_id' => $settings['sso_client_id'] ?? '',
                'client_secret' => $settings['sso_client_secret'] ?? '',
            ]);
            $claims = $this->claims($token, (string) ($settings['sso_userinfo_url'] ?? ''));
            $subject = (string) ($claims['sub'] ?? '');
            $email = strtolower(trim((string) ($claims['email'] ?? '')));
            if ($subject === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The SSO profile must include a verified email address.');
            $db = Database::getInstance();
            $identity = $db->fetch('SELECT * FROM sso_identities WHERE provider="oidc" AND provider_subject=:sub', ['sub' => $subject]);
            $user = $identity ? $db->fetch('SELECT * FROM users WHERE id=:id AND is_active=1 AND deleted_at IS NULL', ['id' => $identity['user_id']]) : $db->fetch('SELECT * FROM users WHERE email=:email AND is_active=1 AND deleted_at IS NULL', ['email' => $email]);
            if (!$user) throw new RuntimeException('No active employee account matches this SSO email.');
            $belongs = Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM employees WHERE user_id=:uid AND company_id=:cid AND deleted_at IS NULL', ['uid' => $user['id'], 'cid' => $companyId]) ?: Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM user_roles WHERE user_id=:uid AND company_id=:cid', ['uid' => $user['id'], 'cid' => $companyId]);
            if (!$belongs && empty($user['is_super_admin'])) throw new RuntimeException('This account is not assigned to the selected company.');
            if ($identity) $db->update('sso_identities', ['last_login_at' => date('Y-m-d H:i:s'), 'email' => $email], 'id=:id', ['id' => $identity['id']]);
            else $db->insert('sso_identities', ['company_id' => $companyId, 'user_id' => $user['id'], 'provider' => 'oidc', 'provider_subject' => $subject, 'email' => $email, 'last_login_at' => date('Y-m-d H:i:s')]);
            $this->auth->login($user, false);
            $this->redirect($this->auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
        } catch (\Throwable $e) {
            flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Single sign-on could not be completed.');
            $this->redirect('/login');
        }
    }

    private function postForm(string $url, array $fields): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('The SSO token endpoint is not configured.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw new RuntimeException('The identity provider token exchange failed' . ($error ? ': ' . $error : '.'));
        return $decoded;
    }

    private function claims(array $token, string $userinfoUrl): array
    {
        if ($userinfoUrl === '' || empty($token['access_token']) || !filter_var($userinfoUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('A secure OpenID Connect UserInfo endpoint is required.');
        }
        $ch = curl_init($userinfoUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token['access_token']]]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw new RuntimeException('The identity provider UserInfo request failed.');
        return $decoded;
    }
}
