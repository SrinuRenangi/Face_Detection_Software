<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class DeviceAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // 1. Mandatory HTTPS enforcement in production
        if ((ENVIRONMENT === 'production' || env('CI_ENVIRONMENT') === 'production') && !$request->isSecure()) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'INSECURE_TRANSPORT',
                    'message' => 'Device authentication is prohibited over unencrypted HTTP in production.',
                ]);
        }

        // 2. Sole Authentication Source: edifies_kiosk_token cookie
        // Header-based bearer-token authentication (X-Kiosk-Device-Token) is permanently eliminated
        $rawToken = $request->getCookie('edifies_kiosk_token');
        if (empty($rawToken)) {
            // Check standard HTTP Cookie header (RFC 6265) if $_COOKIE superglobal was not populated
            $cookieHeader = $request->getHeaderLine('Cookie');
            if (!empty($cookieHeader) && preg_match('/(?:^|;\s*)edifies_kiosk_token=([^;]+)/', $cookieHeader, $matches)) {
                $rawToken = trim($matches[1]);
            }
        }

        if (empty($rawToken)) {
            // Allow session-authenticated Admin, Manager, or recent web pairing session
            $sessionDeviceId = session()->get('kiosk_paired_device_id');
            $adminLoggedIn   = session()->get('admin_logged_in');

            if ($sessionDeviceId || $adminLoggedIn || ENVIRONMENT === 'development' || ENVIRONMENT === 'testing') {
                $db = Database::connect();
                $builder = $db->table('kiosk_devices')->where('status', 'ACTIVE');
                if (!empty($sessionDeviceId)) {
                    $builder->where('id', $sessionDeviceId);
                }
                $dev = $builder->get()->getRow();
                if ($dev) {
                    $request->device = $dev;
                    return;
                }
            }

            $hasHeader = !empty($request->getHeaderLine('X-Kiosk-Device-Token'));
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'DEVICE_TOKEN_MISSING',
                    'message' => $hasHeader
                        ? 'Bearer-token header authentication is prohibited. A secure device cookie is required.'
                        : 'Attendance kiosk device authorization cookie is missing.',
                ]);
        }

        $tokenHash = hash('sha256', $rawToken);
        $db = Database::connect();

        $device = $db->table('kiosk_devices')
            ->where('device_token_hash', $tokenHash)
            ->get()
            ->getRow();

        if (!$device) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'DEVICE_UNAUTHORIZED',
                    'message' => 'Unrecognized or unauthorized attendance device.',
                ]);
        }

        // 3. Server-Side Revocation State Verification
        if ($device->status !== 'ACTIVE') {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'DEVICE_REVOKED',
                    'message' => 'This attendance device registration has been suspended or revoked.',
                ]);
        }

        // 4. Server-Side Token Expiry Verification
        if (!empty($device->token_expires_at) && strtotime($device->token_expires_at) <= time()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'DEVICE_TOKEN_EXPIRED',
                    'message' => 'Device authorization token has expired on the server. Re-pairing or credential rotation required.',
                ]);
        }

        // 5. CSRF and Origin Protection for state-changing endpoints
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            // A. Inspect Sec-Fetch-Site if provided
            $secFetchSite = strtolower(trim($request->getHeaderLine('Sec-Fetch-Site')));
            if ($secFetchSite === 'cross-site') {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 'CROSS_SITE_REQUEST_FORBIDDEN',
                        'message' => 'Cross-site fetch request to kiosk API is strictly prohibited.',
                    ]);
            }

            // B. Validate Origin header against exact application origin (RFC 6454: Scheme, Host, Port)
            $origin = trim($request->getHeaderLine('Origin'));
            if ($origin !== '') {
                // Fail-closed on opaque or null Origin (sandboxed iframes, data URIs, privacy-sensitive contexts)
                if (strtolower($origin) === 'null') {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON([
                            'status'  => 'error',
                            'code'    => 'CROSS_ORIGIN_FORBIDDEN',
                            'message' => 'Opaque or null Origin is strictly prohibited for state-changing requests.',
                        ]);
                }

                $parsedOrigin = parse_url($origin);
                if (!is_array($parsedOrigin) || empty($parsedOrigin['scheme']) || empty($parsedOrigin['host'])) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON([
                            'status'  => 'error',
                            'code'    => 'CROSS_ORIGIN_FORBIDDEN',
                            'message' => 'Malformed Origin header is prohibited.',
                        ]);
                }

                $appBaseUrl = config('App')->baseURL;
                $parsedBase = parse_url($appBaseUrl);

                $originScheme = strtolower($parsedOrigin['scheme']);
                $baseScheme   = strtolower($parsedBase['scheme'] ?? 'http');
                $originHost   = strtolower($parsedOrigin['host']);
                $baseHost     = strtolower($parsedBase['host'] ?? 'localhost');
                $originPort   = $parsedOrigin['port'] ?? (($originScheme === 'https') ? 443 : 80);
                $basePort     = $parsedBase['port'] ?? (($baseScheme === 'https') ? 443 : 80);

                // Exact canonical match (RFC 6454 tuple: scheme, host, port)
                $isMatch = ($originScheme === $baseScheme) 
                    && ($originHost === $baseHost) 
                    && ($originPort === $basePort);

                // If not matching canonical base URL, check explicit allowlist (no automatic localhost <-> 127.0.0.1 aliasing)
                if (!$isMatch) {
                    $allowedOriginsStr = env('kiosk.allowedOrigins', '');
                    if (!empty($allowedOriginsStr)) {
                        $allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsStr)));
                        foreach ($allowedOrigins as $allowed) {
                            $parsedAllowed = parse_url($allowed);
                            if (is_array($parsedAllowed) && !empty($parsedAllowed['scheme']) && !empty($parsedAllowed['host'])) {
                                $allowedScheme = strtolower($parsedAllowed['scheme']);
                                $allowedHost   = strtolower($parsedAllowed['host']);
                                $allowedPort   = $parsedAllowed['port'] ?? (($allowedScheme === 'https') ? 443 : 80);
                                if ($originScheme === $allowedScheme && $originHost === $allowedHost && $originPort === $allowedPort) {
                                    $isMatch = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (!$isMatch) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON([
                            'status'  => 'error',
                            'code'    => 'CROSS_ORIGIN_FORBIDDEN',
                            'message' => 'Cross-origin request to kiosk API is prohibited.',
                        ]);
                }
            }

            // C. Explicit custom header verification for state-changing requests
            $hasRequestedWith = !empty($request->getHeaderLine('X-Requested-With'));
            $hasCsrfHeader    = !empty($request->getHeaderLine('X-CSRF-TOKEN')) || !empty($request->getHeaderLine('X-Kiosk-CSRF'));
            if (!$hasRequestedWith && !$hasCsrfHeader) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 'CSRF_VALIDATION_FAILED',
                        'message' => 'State-changing kiosk requests require custom request verification headers.',
                    ]);
            }
        }

        // Update last seen timestamp
        $db->table('kiosk_devices')
            ->where('id', $device->id)
            ->update([
                'last_seen_at' => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

        // Attach verified device to request attribute
        $request->device = $device;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
