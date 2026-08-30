<?php

namespace App\Licensing;

final class AllowlistNormalizer
{
    public function fromStrings(string $domains, string $hostIps): Allowlist
    {
        $normalizedDomains = $this->normalizeDomains($this->split($domains));
        $normalizedHostIps = $this->normalizeHostIps($this->split($hostIps));

        if ($normalizedDomains === [] && $normalizedHostIps === []) {
            throw InvalidAllowlist::empty();
        }

        return new Allowlist($normalizedDomains, $normalizedHostIps);
    }

    /**
     * @param  array<mixed>  $domains
     * @param  array<mixed>  $hostIps
     */
    public function fromArrays(array $domains, array $hostIps): Allowlist
    {
        if (! $this->containsOnlyStrings($domains) || ! $this->containsOnlyStrings($hostIps)) {
            throw new InvalidAllowlist('Licensed domains and host IPs must be arrays of strings.');
        }

        $normalizedDomains = $this->normalizeDomains($domains);
        $normalizedHostIps = $this->normalizeHostIps($hostIps);

        if ($normalizedDomains === [] && $normalizedHostIps === []) {
            throw InvalidAllowlist::empty();
        }

        return new Allowlist($normalizedDomains, $normalizedHostIps);
    }

    public function normalizeRequestHost(string $host): string
    {
        $host = trim($host);

        if ($host === '' || str_contains($host, ',') || preg_match('/\s/', $host) === 1) {
            throw InvalidAllowlist::domain($host);
        }

        $parsed = parse_url('http://'.$host, PHP_URL_HOST);

        if (! is_string($parsed) || $parsed === '') {
            throw InvalidAllowlist::domain($host);
        }

        if (str_starts_with($parsed, '[') && str_ends_with($parsed, ']')) {
            $parsed = substr($parsed, 1, -1);
        }

        $parsed = strtolower(rtrim($parsed, '.'));

        if (filter_var($parsed, FILTER_VALIDATE_IP) !== false) {
            return $this->canonicalIp($parsed);
        }

        if (! $this->isValidDomain($parsed)) {
            throw InvalidAllowlist::domain($host);
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function split(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $entry): bool => $entry !== '',
        ));
    }

    /**
     * @param  list<string>  $domains
     * @return list<string>
     */
    private function normalizeDomains(array $domains): array
    {
        $normalized = [];

        foreach ($domains as $domain) {
            $domain = strtolower(rtrim(trim($domain), '.'));
            $wildcard = str_starts_with($domain, '*.');
            $base = $wildcard ? substr($domain, 2) : $domain;

            if ($domain === '' || str_contains($base, '*') || ! $this->isValidDomain($base)) {
                throw InvalidAllowlist::domain($domain);
            }

            $normalized[] = $wildcard ? '*.'.$base : $base;
        }

        return $this->sortUnique($normalized);
    }

    /**
     * @param  list<string>  $hostIps
     * @return list<string>
     */
    private function normalizeHostIps(array $hostIps): array
    {
        $normalized = [];

        foreach ($hostIps as $entry) {
            $entry = trim($entry);

            if (! str_contains($entry, '/')) {
                if (filter_var($entry, FILTER_VALIDATE_IP) === false) {
                    throw InvalidAllowlist::ip($entry);
                }

                $normalized[] = $this->canonicalIp($entry);

                continue;
            }

            [$address, $prefix] = array_pad(explode('/', $entry, 2), 2, null);
            $packed = is_string($address) ? @inet_pton($address) : false;

            if ($packed === false || ! is_string($prefix) || ! ctype_digit($prefix)) {
                throw InvalidAllowlist::ip($entry);
            }

            $prefixLength = (int) $prefix;
            $maximum = strlen($packed) * 8;

            if ($prefixLength < 0 || $prefixLength > $maximum) {
                throw InvalidAllowlist::ip($entry);
            }

            $network = $this->maskPackedAddress($packed, $prefixLength);
            $canonical = inet_ntop($network);

            if ($canonical === false) {
                throw InvalidAllowlist::ip($entry);
            }

            $normalized[] = strtolower($canonical).'/'.$prefixLength;
        }

        return $this->sortUnique($normalized);
    }

    private function canonicalIp(string $address): string
    {
        $packed = @inet_pton($address);
        $canonical = $packed === false ? false : inet_ntop($packed);

        if ($canonical === false) {
            throw InvalidAllowlist::ip($address);
        }

        return strtolower($canonical);
    }

    private function maskPackedAddress(string $packed, int $prefixLength): string
    {
        $bytes = unpack('C*', $packed);

        if ($bytes === false) {
            return $packed;
        }

        foreach ($bytes as $position => $byte) {
            $bitsBeforeByte = ($position - 1) * 8;
            $remaining = $prefixLength - $bitsBeforeByte;

            if ($remaining >= 8) {
                continue;
            }

            if ($remaining <= 0) {
                $bytes[$position] = 0;

                continue;
            }

            $bytes[$position] = $byte & (0xFF << (8 - $remaining));
        }

        return pack('C*', ...array_values($bytes));
    }

    private function isValidDomain(string $domain): bool
    {
        if ($domain === '' || strlen($domain) > 253 || filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $labels = explode('.', $domain);

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63 || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sortUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_STRING);

        return $values;
    }

    /** @param array<mixed> $values */
    private function containsOnlyStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                return false;
            }
        }

        return true;
    }
}
