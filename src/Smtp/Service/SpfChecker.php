<?php

namespace App\Smtp\Service;

class SpfChecker
{
    private const MAX_DNS_LOOKUPS = 10;

    public function check(string $ip, string $domain, int $depth = 0): bool
    {
        if ($depth > self::MAX_DNS_LOOKUPS) {
            throw new \Exception('SPF PERMERROR: too many DNS lookups');
        }

        $records = $this->getDnsRecords($domain, DNS_TXT);
        if ($records === false) {
            throw new \Exception(\sprintf('SPF TEMPERROR: DNS lookup failed for %s', $domain));
        }

        $spf = [];
        foreach ($records as $r) {
            if (isset($r['txt']) && \str_starts_with($r['txt'], 'v=spf1')) {
                $spf[] = $r['txt'];
            }
        }
        if (\count($spf) === 0) {
            foreach ($records as $r) {
                if (isset($r['txt']) && \str_starts_with($r['txt'], 'spf1')) {
                    throw new \Exception('Invalid SPF record');
                }
            }
            throw new \Exception(\sprintf('No SPF record found for %s', $domain));
        }

        if (\count($spf) > 1) {
            throw new \Exception('Multiple SPF records found');
        }

        $spf = $spf[0];

        if (\preg_match_all('/ip4:([0-9\.\/]+)/', $spf, $m)) {
            foreach ($m[1] as $cidr) {
                if ($this->ipInCidr($ip, $cidr)) {
                    return true;
                }
            }
        }

        if (\preg_match_all('/include:([^\s]+)/', $spf, $m)) {
            foreach ($m[1] as $inc) {
                if ($this->check($ip, $inc, $depth + 1)) {
                    return true;
                }
            }
        }

        if (\preg_match('/([~\-\+\?])?all/', $spf, $m)) {
            $qualifier = $m[1] ?? '+';

            return match ($qualifier) {
                '+', '' => true,
                '-', '~', '?' => false,
                default => false,
            };
        }

        return false;
    }

    protected function getDnsRecords(string $host, int $type): array|false
    {
        return \dns_get_record($host, $type);
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if (false === \str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = \explode('/', $cidr);
        $mask = 0xffffffff << (32 - (int)$mask);
        return ((\ip2long($ip) & $mask) === (\ip2long($subnet) & $mask));
    }
}
