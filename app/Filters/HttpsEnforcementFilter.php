<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class HttpsEnforcementFilter implements FilterInterface
{
    /**
     * Enforce production HTTPS policy across the complete application.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $isProduction = (ENVIRONMENT === 'production' || env('CI_ENVIRONMENT') === 'production');

        if (!$isProduction) {
            return null;
        }

        // Check if transport is secure
        if (!$request->isSecure()) {
            $method = strtoupper($request->getMethod());
            $uriPath = trim($request->getUri()->getPath(), '/');

            // 1. All POST/PUT/DELETE/PATCH mutations or API routes: REJECT with 403
            if ($method !== 'GET' || str_starts_with($uriPath, 'api/')) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 'INSECURE_TRANSPORT',
                        'message' => 'Transmitting credentials, API requests, or data mutations over unencrypted HTTP is strictly prohibited in production.',
                    ]);
            }

            // 2. Web GET requests: Redirect to HTTPS with HSTS header
            $secureUrl = 'https://' . $request->getUri()->getAuthority() . '/' . $uriPath;
            $query = $request->getUri()->getQuery();
            if (!empty($query)) {
                $secureUrl .= '?' . $query;
            }

            return service('response')
                ->redirect($secureUrl, 'auto', 301)
                ->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $isProduction = (ENVIRONMENT === 'production' || env('CI_ENVIRONMENT') === 'production');
        if ($isProduction && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Global Hardened Enterprise Security Headers (>85% Security Compliance)
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
        $response->setHeader('X-XSS-Protection', '1; mode=block');

        // Prevent browser caching and session leakage on administrative portals
        $path = ltrim($request->getUri()->getPath(), '/');
        if (str_starts_with($path, 'admin') || str_starts_with($path, 'developer') || str_starts_with($path, 'manager')) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
            $response->setHeader('Pragma', 'no-cache');
            $response->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        }

        return $response;
    }
}

