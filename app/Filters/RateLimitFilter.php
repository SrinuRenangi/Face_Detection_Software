<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RateLimitFilter implements FilterInterface
{
    private const MAX_USER_IP_ATTEMPTS = 5;
    private const MAX_GLOBAL_IP_ATTEMPTS = 15;
    private const DECAY_SECONDS = 900; // 15 minutes

    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return;
        }

        $cache = service('cache');
        $ip = $request->getIPAddress();
        $rawUsername = (string) $request->getPost('username');
        $normUsername = strtolower(substr(trim($rawUsername), 0, 64));

        $userIpHash = md5($ip . '_' . $normUsername);
        $globalIpHash = md5($ip);

        $now = time();

        // 1. Check account-specific lockout on this network
        $userLockoutExpiry = $cache->get('lockout_uip_' . $userIpHash);
        if ($userLockoutExpiry && $userLockoutExpiry > $now) {
            $remaining = max(1, $userLockoutExpiry - $now);
            $minutes = ceil($remaining / 60);
            return $this->rateLimitExceededResponse($request, "Too many failed login attempts for this account from your network. Account temporarily locked. Please try again in {$minutes} minute(s).");
        }

        // 2. Check global network IP lockout (protection against distributed credential stuffing)
        $globalLockoutExpiry = $cache->get('lockout_ip_' . $globalIpHash);
        if ($globalLockoutExpiry && $globalLockoutExpiry > $now) {
            $remaining = max(1, $globalLockoutExpiry - $now);
            $minutes = ceil($remaining / 60);
            return $this->rateLimitExceededResponse($request, "Too many failed authentication attempts from this network. Temporarily restricted. Please try again in {$minutes} minute(s).");
        }

        // 3. Check attempt thresholds
        $userAttempts = (int) $cache->get('login_uip_' . $userIpHash);
        if ($userAttempts >= self::MAX_USER_IP_ATTEMPTS) {
            $cache->save('lockout_uip_' . $userIpHash, $now + self::DECAY_SECONDS, self::DECAY_SECONDS);
            $minutes = ceil(self::DECAY_SECONDS / 60);
            return $this->rateLimitExceededResponse($request, "Too many failed login attempts for this account from your network. Account temporarily locked. Please try again in {$minutes} minute(s).");
        }

        $globalAttempts = (int) $cache->get('login_ip_' . $globalIpHash);
        if ($globalAttempts >= self::MAX_GLOBAL_IP_ATTEMPTS) {
            $cache->save('lockout_ip_' . $globalIpHash, $now + self::DECAY_SECONDS, self::DECAY_SECONDS);
            $minutes = ceil(self::DECAY_SECONDS / 60);
            return $this->rateLimitExceededResponse($request, "Too many failed authentication attempts from this network. Temporarily restricted. Please try again in {$minutes} minute(s).");
        }
    }

    private function rateLimitExceededResponse(RequestInterface $request, string $message)
    {
        if ($request->isAJAX() || str_starts_with(ltrim($request->getUri()->getPath(), '/'), 'api/')) {
            return service('response')
                ->setStatusCode(429)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'RATE_LIMIT_EXCEEDED',
                    'message' => $message,
                ]);
        }

        return redirect()->to('/admin/login')->with('error', $message);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}

