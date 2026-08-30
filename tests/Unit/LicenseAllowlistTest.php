<?php

namespace Tests\Unit;

use App\Licensing\AllowlistNormalizer;
use App\Licensing\HostMatcher;
use App\Licensing\InvalidAllowlist;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LicenseAllowlistTest extends TestCase
{
    private AllowlistNormalizer $normalizer;

    private HostMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new AllowlistNormalizer;
        $this->matcher = new HostMatcher;
    }

    public function test_allowlists_are_canonical_sets(): void
    {
        $allowlist = $this->normalizer->fromStrings(
            ' Example.COM,*.Sub.Example.com,example.com ',
            '192.168.1.29/24, 127.0.0.1, 0:0:0:0:0:0:0:1',
        );

        $this->assertSame(['*.sub.example.com', 'example.com'], $allowlist->domains);
        $this->assertSame(['127.0.0.1', '192.168.1.0/24', '::1'], $allowlist->hostIps);
    }

    public function test_wildcard_matches_subdomains_but_not_apex_or_unrelated_suffixes(): void
    {
        $allowlist = $this->normalizer->fromStrings('*.tarunabakti.or.id', '');

        $this->assertTrue($this->matcher->matches('app.tarunabakti.or.id', $allowlist));
        $this->assertTrue($this->matcher->matches('deep.app.tarunabakti.or.id', $allowlist));
        $this->assertFalse($this->matcher->matches('tarunabakti.or.id', $allowlist));
        $this->assertFalse($this->matcher->matches('evil-tarunabakti.or.id', $allowlist));
    }

    #[DataProvider('invalidDomainProvider')]
    public function test_unsafe_domain_patterns_are_rejected(string $domain): void
    {
        $this->expectException(InvalidAllowlist::class);

        $this->normalizer->fromStrings($domain, '');
    }

    /** @return array<string, array{string}> */
    public static function invalidDomainProvider(): array
    {
        return [
            'loose wildcard' => ['*tarunabakti.sch.id'],
            'scheme' => ['https://example.com'],
            'port' => ['example.com:443'],
            'path' => ['example.com/admin'],
            'embedded wildcard' => ['foo.*.example.com'],
        ];
    }

    public function test_ipv4_and_ipv6_cidr_boundaries_are_enforced(): void
    {
        $allowlist = $this->normalizer->fromStrings('', '192.168.10.0/24,2001:db8::/32');

        $this->assertTrue($this->matcher->matches('192.168.10.0', $allowlist));
        $this->assertTrue($this->matcher->matches('192.168.10.255', $allowlist));
        $this->assertFalse($this->matcher->matches('192.168.11.0', $allowlist));
        $this->assertTrue($this->matcher->matches('2001:db8::ffff', $allowlist));
        $this->assertFalse($this->matcher->matches('2001:db9::1', $allowlist));
    }

    public function test_bracketed_ipv6_request_host_with_port_is_normalized(): void
    {
        $this->assertSame('::1', $this->normalizer->normalizeRequestHost('[::1]:8443'));
    }
}
