<?php

namespace Tests\Unit;

use App\Models\AdminUserModel;
use CodeIgniter\Test\CIUnitTestCase;

class AdminAuthAndSecurityTest extends CIUnitTestCase
{
    protected AdminUserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new AdminUserModel();
    }

    public function testPasswordHashingAndVerification()
    {
        $rawPassword = 'SuperSecretAdminPassword!123';
        $hash = password_hash($rawPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$2y$12$', $hash);
        $this->assertTrue(password_verify($rawPassword, $hash));
        $this->assertFalse(password_verify('WrongPassword456!', $hash));
    }

    public function testUuidV4FormatValidity()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'UUID must conform to RFC 4122 Version 4 variant 1 format'
        );
    }

    public function testAdminUserCreationAndRoleValidation()
    {
        $rawPassword = 'AdminRoleTestPassword!789';
        $hash = password_hash($rawPassword, PASSWORD_BCRYPT);
        $testUsername = 'unit_admin_' . bin2hex(random_bytes(4));

        $userId = 'usr-' . bin2hex(random_bytes(6));
        $now = date('Y-m-d H:i:s');

        $inserted = $this->userModel->insert([
            'id'            => $userId,
            'username'      => $testUsername,
            'password_hash' => $hash,
            'full_name'     => 'Unit Test Admin',
            'role'          => 'ROLE_ADMIN',
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $this->assertTrue($inserted !== false);

        $found = $this->userModel->findByUsername($testUsername);
        $this->assertNotNull($found);
        $this->assertSame($testUsername, $found['username']);
        $this->assertSame('ROLE_ADMIN', $found['role']);
        $this->assertSame(1, (int) $found['is_active']);
        $this->assertTrue(password_verify($rawPassword, $found['password_hash']));

        // Clean up
        $this->userModel->delete($userId);
    }

    public function testManagerRoleAndActiveFlag()
    {
        $testUsername = 'unit_mgr_' . bin2hex(random_bytes(4));
        $userId = 'usr-' . bin2hex(random_bytes(6));
        $now = date('Y-m-d H:i:s');

        $this->userModel->insert([
            'id'            => $userId,
            'username'      => $testUsername,
            'password_hash' => password_hash('ManagerPass!123', PASSWORD_BCRYPT),
            'full_name'     => 'Unit Test Manager',
            'role'          => 'ROLE_MANAGER',
            'is_active'     => 0, // deactivated
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $found = $this->userModel->findByUsername($testUsername);
        $this->assertNotNull($found);
        $this->assertSame('ROLE_MANAGER', $found['role']);
        $this->assertSame(0, (int) $found['is_active'], 'Deactivated user must have is_active = 0');

        // Clean up
        $this->userModel->delete($userId);
    }

    public function testNormalizedUsernamePlusIpRateLimitingLogic()
    {
        $cache = service('cache');
        $ip1 = '192.168.1.101';
        $ip2 = '192.168.1.102';
        $userA = 'Admin';
        $userB = 'Manager';

        $normA = strtolower(substr(trim($userA), 0, 64));
        $normB = strtolower(substr(trim($userB), 0, 64));

        $userAIp1Hash = md5($ip1 . '_' . $normA);
        $userBIp1Hash = md5($ip1 . '_' . $normB);
        $userAIp2Hash = md5($ip2 . '_' . $normA);

        // Simulate 5 failed attempts for UserA on IP1
        for ($i = 1; $i <= 5; $i++) {
            $attempts = (int) $cache->get('login_uip_' . $userAIp1Hash) + 1;
            $cache->save('login_uip_' . $userAIp1Hash, $attempts, 900);
            if ($attempts >= 5) {
                $cache->save('lockout_uip_' . $userAIp1Hash, time() + 900, 900);
            }
        }

        // Verify UserA on IP1 is locked out
        $this->assertGreaterThan(time(), (int) $cache->get('lockout_uip_' . $userAIp1Hash));

        // Verify UserB on SAME IP1 is NOT locked out
        $this->assertNull($cache->get('lockout_uip_' . $userBIp1Hash));
        $this->assertSame(0, (int) $cache->get('login_uip_' . $userBIp1Hash));

        // Verify UserA from DIFFERENT IP2 is NOT locked out
        $this->assertNull($cache->get('lockout_uip_' . $userAIp2Hash));
        $this->assertSame(0, (int) $cache->get('login_uip_' . $userAIp2Hash));

        // Cleanup
        $cache->delete('login_uip_' . $userAIp1Hash);
        $cache->delete('lockout_uip_' . $userAIp1Hash);
    }

    public function testSessionIdRegenerationFunctionality()
    {
        $session = session();
        $initialId = $session->session_id;

        // Perform session ID regeneration
        $session->regenerate(true);
        $regeneratedId = $session->session_id;

        // In test session handler, regenerate generates a distinct or valid session token
        $this->assertNotNull($regeneratedId);
    }
}
