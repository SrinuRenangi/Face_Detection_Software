<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ResetDatabaseCommand extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:reset-records';
    protected $description = 'Safely purges transactional attendance logs and resets database to a clean testing state.';
    protected $usage       = 'db:reset-records [--all]';
    protected $arguments   = [];
    protected $options     = [
        '--all' => 'Also purge employee directory and enrolled face biometric templates (complete wipe).',
    ];

    public function run(array $params)
    {
        $purgeAll = array_key_exists('all', $params) || CLI::getOption('all');

        CLI::write('---------------------------------------------------------', 'yellow');
        CLI::write('   Edifies School: Safe Database Record Reset Utility   ', 'green');
        CLI::write('---------------------------------------------------------', 'yellow');

        if ($purgeAll) {
            CLI::write('WARNING: --all specified. ALL employee records and biometric face templates will be purged!', 'red');
        } else {
            CLI::write('Mode: Purging attendance history, event ledger, photos, and device pairing tokens.', 'cyan');
            CLI::write('Note: Employee directory and enrolled biometric face templates are PRESERVED.', 'cyan');
        }

        $db = \Config\Database::connect();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        $transactionalTables = [
            'attendance_events',
            'attendance_records',
            'attendance_photos',
            'attendance_corrections',
            'attendance_review_decisions',
            'idempotency_keys',
            'kiosk_pairing_codes',
            'kiosk_time_anchors',
        ];

        foreach ($transactionalTables as $table) {
            if ($db->tableExists($table)) {
                $count = $db->table($table)->countAllResults();
                $db->table($table)->truncate();
                CLI::write("Truncated {$table} ({$count} records removed).", 'light_gray');
            }
        }

        if ($purgeAll) {
            $employeeTables = [
                'employee_face_templates',
                'employees',
                'admin_audit_logs',
                'kiosk_devices',
            ];
            foreach ($employeeTables as $table) {
                if ($db->tableExists($table)) {
                    $count = $db->table($table)->countAllResults();
                    $db->table($table)->truncate();
                    CLI::write("Truncated {$table} ({$count} records removed).", 'red');
                }
            }
        } else {
            // Reset any pending kiosk pairing codes, but retain registered active devices
            if ($db->tableExists('kiosk_devices')) {
                $db->table('kiosk_devices')->where('status', 'PENDING_PAIRING')->delete();
            }
        }

        // Purge stored physical attendance photo files on disk recursively (including partitioned YYYY/MM/ subdirectories)
        $photoDir = WRITEPATH . 'attendance_photos';
        if (is_dir($photoDir)) {
            $removedFiles = 0;
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($photoDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $item) {
                    if ($item->isFile()) {
                        if (str_ends_with(strtolower($item->getFilename()), '.jpg')) {
                            @unlink($item->getPathname());
                            $removedFiles++;
                        }
                    } elseif ($item->isDir()) {
                        @rmdir($item->getPathname());
                    }
                }
            } catch (\Throwable $e) {
                // Ignore filesystem traversal errors
            }
            if ($removedFiles > 0) {
                CLI::write("Purged {$removedFiles} physical attendance photo files from disk.", 'light_gray');
            }
        }

        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Ensure system settings table has default killswitch = 0
        if ($db->tableExists('system_settings')) {
            $db->table('system_settings')->where('setting_key', 'app_killswitch_active')->update([
                'setting_value' => '0',
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        // Ensure default administrative accounts exist with Bcrypt hashes
        $adminUserCount = $db->table('admin_users')->countAllResults();
        if ($adminUserCount === 0) {
            CLI::write('No admin accounts found. Seeding initial accounts from environment...', 'yellow');
            $adminModel = new \App\Models\AdminUserModel();
            $adminModel->seedInitialAccounts();
            CLI::write('Initial accounts created.', 'green');
        }

        CLI::newLine();
        CLI::write('Database reset completed successfully. Clean state established for testing.', 'green');
        CLI::write('---------------------------------------------------------', 'yellow');
    }
}

