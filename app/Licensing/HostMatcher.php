<?php

namespace App\Licensing;

final class HostMatcher
{
    public function matches(string $host, Allowlist $allowlist): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->matchesIp($host, $allowlist->hostIps);
        }

        foreach ($allowlist->domains as $pattern) {
            if (! str_starts_with($pattern, '*.')) {
                if (hash_equals($pattern, $host)) {
                    return true;
                }

                continue;
            }

            $base = substr($pattern, 2);

            if ($host !== $base && str_ends_with($host, '.'.$base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $entries
     */
    private function matchesIp(string $host, array $entries): bool
    {
        $packedHost = @inet_pton($host);

        if ($packedHost === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if (! str_contains($entry, '/')) {
                $packedEntry = @inet_pton($entry);

                if ($packedEntry !== false && hash_equals($packedEntry, $packedHost)) {
                    return true;
                }

                continue;
            }

            [$network, $prefix] = explode('/', $entry, 2);
            $packedNetwork = @inet_pton($network);

            if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packedHost)) {
                continue;
            }

            if ($this->networkContains($packedNetwork, $packedHost, (int) $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function networkContains(string $network, string $address, int $prefixLength): bool
    {
        $wholeBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($wholeBytes > 0 && ! hash_equals(substr($network, 0, $wholeBytes), substr($address, 0, $wholeBytes))) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits);

        return (ord($network[$wholeBytes]) & $mask) === (ord($address[$wholeBytes]) & $mask);
    }
}
