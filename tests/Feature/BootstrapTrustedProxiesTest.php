<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfigureTrustedProxies;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BootstrapTrustedProxiesTest extends TestCase
{
    public function test_http_kernel_resolves_before_config_repository_is_loaded(): void
    {
        // A separate process guarantees that the current PHPUnit app cannot
        // hide the early-bootstrap bug by providing its already-loaded config.
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class);
echo 'kernel-ready';
PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString('kernel-ready', $process->getOutput());
    }

    public function test_trusted_proxy_is_read_from_laravel_config_when_request_arrives(): void
    {
        config()->set('security.trusted_proxies', '127.0.0.1,::1');

        $request = $this->requestFrom('127.0.0.1');
        (new ConfigureTrustedProxies)->handle($request, fn ($request) => $request);

        $this->assertSame('203.0.113.15', $request->ip());
        $this->assertTrue($request->isSecure());
        // X-Forwarded-Host is intentionally excluded to prevent host spoofing.
        $this->assertSame('invman.test', $request->getHost());
    }

    public function test_untrusted_proxy_and_wildcard_configuration_are_fail_closed(): void
    {
        config()->set('security.trusted_proxies', '127.0.0.1');
        $request = $this->requestFrom('198.51.100.55');
        (new ConfigureTrustedProxies)->handle($request, fn ($request) => $request);
        $this->assertSame('198.51.100.55', $request->ip());
        $this->assertFalse($request->isSecure());

        config()->set('security.trusted_proxies', '*');
        $request = $this->requestFrom('127.0.0.1');
        (new ConfigureTrustedProxies)->handle($request, fn ($request) => $request);
        $this->assertSame('127.0.0.1', $request->ip());
        $this->assertFalse($request->isSecure());
    }

    public function test_cidr_config_and_invalid_proxy_ranges(): void
    {
        config()->set('security.trusted_proxies', '10.0.0.0/8,::1');
        $request = $this->requestFrom('10.1.1.6');
        (new ConfigureTrustedProxies)->handle($request, fn ($request) => $request);
        $this->assertSame('203.0.113.15', $request->ip());

        config()->set('security.trusted_proxies', '0.0.0.0/0');
        $request = $this->requestFrom('10.1.1.6');
        (new ConfigureTrustedProxies)->handle($request, fn ($request) => $request);
        $this->assertSame('10.1.1.6', $request->ip());
    }

    private function requestFrom(string $remote): Request
    {
        return Request::create('http://invman.test/up', 'GET', server: [
            'REMOTE_ADDR' => $remote,
            'HTTP_X_FORWARDED_FOR' => '203.0.113.15',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
        ]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }
}
