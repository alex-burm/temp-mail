<?php

namespace App\Smtp\Service\SpfChecker;

class SpfChecker
{
    private const MAX_DNS_LOOKUPS = 10;

    public function check(string $ip, string $domain, int $depth = 0): SpfResult
    {
        if ($depth > self::MAX_DNS_LOOKUPS) {
            return new SpfResult(
                SpfResultStatus::PERMERROR,
                'Too many DNS lookups'
            );
        }

        $records = $this->getDnsRecords($domain, DNS_TXT);
        if ($records === false) {
            return new SpfResult(
                SpfResultStatus::TEMPERROR,
                \sprintf('DNS lookup failed for %s', $domain)
            );
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
                    return new SpfResult(
                        SpfResultStatus::PERMERROR,
                        \sprintf('Invalid SPF record for %s', $domain)
                    );
                }
            }
            return new SpfResult(
                SpfResultStatus::NONE,
                \sprintf('No SPF record found for %s', $domain)
            );
        }

        if (\count($spf) > 1) {
            return new SpfResult(
                SpfResultStatus::PERMERROR,
                sprintf('Multiple SPF records found for %s', $domain)
            );
        }

        $spf = $spf[0];

        if (\preg_match_all('/ip4:([0-9\.\/]+)/', $spf, $m)) {
            foreach ($m[1] as $cidr) {
                if ($this->ipInCidr($ip, $cidr)) {
                    return new SpfResult(
                        SpfResultStatus::PASS,
                        \sprintf('IP %s matches ip4:%s in SPF record', $ip, $cidr)
                    );
                }
            }
        }

        if (\preg_match_all('/include:([^\s]+)/', $spf, $m)) {
            foreach ($m[1] as $inc) {
                $result = $this->check($ip, $inc, $depth + 1);

                if ($result->status === SpfResultStatus::PERMERROR) {
                    return $result;
                }

                if ($result->status === SpfResultStatus::PASS) {
                    return new SpfResult(
                        SpfResultStatus::PASS,
                        \sprintf('IP %s authorized via include:%s', $ip, $inc)
                    );
                }
            }
        }

        if (\preg_match('/([~\-\+\?])?all/', $spf, $m)) {
            $qualifier = $m[1] ?? '+';

            return match ($qualifier) {
                '+', '' => new SpfResult(
                    SpfResultStatus::PASS,
                    \sprintf('SPF record allows all (+all) for %s', $domain)
                ),
                '-' => new SpfResult(
                    SpfResultStatus::FAIL,
                    \sprintf('SPF record forbids this IP (-all) for %s', $domain)
                ),
                '~' => new SpfResult(
                    SpfResultStatus::SOFTFAIL,
                    \sprintf('SPF record soft-fails (~all) for %s', $domain)
                ),
                '?' => new SpfResult(
                    SpfResultStatus::NEUTRAL,
                    \sprintf('SPF record is neutral (?all) for %s', $domain)
                ),
                default => new SpfResult(
                    SpfResultStatus::NEUTRAL,
                    \sprintf('Unknown SPF qualifier "%s" for %s', $qualifier, $domain)
                ),
            };
        }

        return new SpfResult(
            SpfResultStatus::NEUTRAL,
            \sprintf('No matching rule found for IP %s in %s', $ip, $domain)
        );
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
