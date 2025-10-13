<?php

namespace App\Smtp\Service\SpfChecker;

class SpfChecker
{
    private const MAX_DNS_LOOKUPS = 10;

    public function check(string $ip, string $domain, int $depth = 0): SpfResult
    {
        if ($depth > self::MAX_DNS_LOOKUPS) {
            return $this->error(SpfResultStatus::PERMERROR, 'Too many DNS lookups');
        }

        $spf = $this->resolveRecord($domain);
        if ($spf instanceof SpfResult) {
            return $spf;
        }

        $ipCheck = $this->checkIpMatch($ip, $spf);
        if (false === \is_null($ipCheck)) {
            return $ipCheck;
        }

        $includeResult = $this->checkIncludeMechanisms($ip, $spf, $depth);
        if (false === \is_null($includeResult)) {
            return $includeResult;
        }

        if (\preg_match('/([~\-\+\?])?all/', $spf, $m)) {
            $qualifier = $m[1] ?? '+';
            return $this->qualifierResult($qualifier, $domain);
        }

        return $this->neutral(\sprintf('No matching rule found for IP %s in %s', $ip, $domain));
    }

    private function resolveRecord(string $domain): string|SpfResult
    {
        $records = $this->getDnsRecords($domain, DNS_TXT);
        if (false === $records) {
            return $this->error(SpfResultStatus::TEMPERROR, \sprintf('DNS lookup failed for %s', $domain));
        }

        $spf = \array_values(
            \array_filter(
                $records,
                static fn($r) => isset($r['txt']) && \str_starts_with($r['txt'], 'v=spf1')
            )
        );

        if (\count($spf) === 0) {
            foreach ($records as $r) {
                if (isset($r['txt']) && \str_starts_with($r['txt'], 'spf1')) {
                    return $this->error(SpfResultStatus::PERMERROR, \sprintf('Invalid SPF record for %s', $domain));
                }
            }
            return $this->error(SpfResultStatus::NONE, \sprintf('No SPF record found for %s', $domain));
        }

        if (\count($spf) > 1) {
            return $this->error(SpfResultStatus::PERMERROR, \sprintf('Multiple SPF records found for %s', $domain));
        }

        return $spf[0]['txt'];
    }

    private function checkIpMatch(string $ip, string $spf): ?SpfResult
    {
        $validators = [
            'ip4' => 'ip4InCidr',
            'ip6' => 'ip6InCidr',
        ];

        foreach ($validators as $mechanism => $method) {
            $pattern = '/' . $mechanism . ':([0-9A-Fa-f\.:\/]+)/';
            if (false === \preg_match_all($pattern, $spf, $matches)) {
                continue;
            }

            foreach ($matches[1] as $cidr) {
                if ($this->$method($ip, $cidr)) {
                    return $this->pass(\sprintf('IP %s matches %s:%s in SPF record', $ip, $mechanism, $cidr));
                }
            }
        }

        return null;
    }

    private function checkIncludeMechanisms(string $ip, string $spf, int $depth): ?SpfResult
    {
        if (false === \preg_match_all('/include:([^\s]+)/', $spf, $matches)) {
            return null;
        }

        foreach ($matches[1] as $inc) {
            $result = $this->check($ip, $inc, $depth + 1);

            if ($result->status === SpfResultStatus::PERMERROR) {
                return $result;
            }

            if ($result->status === SpfResultStatus::PASS) {
                return $this->pass(\sprintf('IP %s authorized via include:%s', $ip, $inc));
            }
        }

        return null;
    }

    protected function getDnsRecords(string $host, int $type): array|false
    {
        return \dns_get_record($host, $type);
    }

    protected function ip4InCidr(string $ip, string $cidr): bool
    {
        if (false === \str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = \explode('/', $cidr);
        $mask = 0xffffffff << (32 - (int)$mask);
        return ((\ip2long($ip) & $mask) === (\ip2long($subnet) & $mask));
    }

    protected function ip6InCidr(string $ip, string $cidr): bool
    {
        if (false === \str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = \explode('/', $cidr);
        $mask = (int)$mask;

        $ipBin = \inet_pton($ip);
        $subnetBin = \inet_pton($subnet);
        if (false === $ipBin || false === $subnetBin) {
            return false;
        }

        $bytes = \intdiv($mask, 8);
        $bits = $mask % 8;

        if (\strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $maskByte = ~(0xff >> $bits) & 0xff;
        return (\ord($ipBin[$bytes]) & $maskByte) === (\ord($subnetBin[$bytes]) & $maskByte);
    }

    private function qualifierResult(string $qualifier, string $domain): SpfResult
    {
        return match ($qualifier) {
            '+', '' => $this->pass(\sprintf('SPF record allows all (+all) for %s', $domain)),
            '-' => $this->fail(\sprintf('SPF record forbids this IP (-all) for %s', $domain)),
            '~' => $this->softfail(\sprintf('SPF record soft-fails (~all) for %s', $domain)),
            '?' => $this->neutral(\sprintf('SPF record is neutral (?all) for %s', $domain)),
            default => $this->neutral(\sprintf('Unknown SPF qualifier "%s" for %s', $qualifier, $domain)),
        };
    }

    private function pass(string $msg): SpfResult
    {
        return new SpfResult(SpfResultStatus::PASS, $msg);
    }

    private function fail(string $msg): SpfResult
    {
        return new SpfResult(SpfResultStatus::FAIL, $msg);
    }

    private function softfail(string $msg): SpfResult
    {
        return new SpfResult(SpfResultStatus::SOFTFAIL, $msg);
    }

    private function neutral(string $msg): SpfResult
    {
        return new SpfResult(SpfResultStatus::NEUTRAL, $msg);
    }

    private function error(SpfResultStatus $status, string $msg): SpfResult
    {
        return new SpfResult($status, $msg);
    }
}
