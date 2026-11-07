<?php

namespace Tests\Concurrency;

use CodeIgniter\Test\CIUnitTestCase;

class MultiProcessConcurrencyTest extends CIUnitTestCase
{
    public function testAllNineMultiProcessConcurrencyScenariosPass()
    {
        $runnerScript = realpath(__DIR__ . '/run_multi_process_concurrency_test.php');
        $phpBin = 'C:\\tools\\php85\\php.exe';

        $output = [];
        $exitCode = 0;
        exec("\"{$phpBin}\" \"{$runnerScript}\"", $output, $exitCode);

        $outputStr = implode("\n", $output);

        $this->assertSame(0, $exitCode, "Multi-process concurrency harness exited with non-zero code. Output:\n" . $outputStr);
        $this->assertStringContainsString('ALL 9 CONCURRENCY SCENARIOS PASSED (100% SUCCESS)', $outputStr);
    }
}

