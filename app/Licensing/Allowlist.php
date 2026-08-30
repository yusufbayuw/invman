<?php

namespace App\Licensing;

final readonly class Allowlist
{
    /**
     * @param  list<string>  $domains
     * @param  list<string>  $hostIps
     */
    public function __construct(
        public array $domains,
        public array $hostIps,
    ) {}

    /**
     * @return array{domains: list<string>, host_ips: list<string>}
     */
    public function toArray(): array
    {
        return [
            'domains' => $this->domains,
            'host_ips' => $this->hostIps,
        ];
    }
}
