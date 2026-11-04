<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class StandaloneProbeGuardTest extends TestCase
{
    public function test_disallowed_probe_execution_has_a_failing_process_exit_code(): void
    {
        foreach (['tests/Support/work-order-concurrency.php', 'tests/Support/work-order-upgrade.php', 'tests/Support/Readiness/authority-concurrency.php'] as $script) {
            $process = new Process([PHP_BINARY, '-c', php_ini_loaded_file(), $script], base_path(), [
                'APP_ENV' => 'testing', 'INVMAN_CONCURRENCY_TEST' => '0', 'INVMAN_UPGRADE_TEST' => '0', 'INVMAN_FINAL_QA' => '0',
            ]);
            $process->run();
            $this->assertSame(1, $process->getExitCode(), $script.' must fail closed outside the opt-in QA environment');
            $this->assertStringContainsString('Readiness probe failed:', $process->getErrorOutput());
        }
    }
}
