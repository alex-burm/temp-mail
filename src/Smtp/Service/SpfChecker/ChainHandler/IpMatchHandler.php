<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultFactory;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

class IpMatchHandler extends AbstractSpfHandler
{
    public function handle(SpfContext $context): ?SpfResult
    {
        $spf = $context->record;
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
                if ($this->$method($context->ip, $cidr)) {
                    return (new SpfResultFactory($context->domain))->make(
                        SpfResultStatus::PASS,
                        \sprintf('IP %s matches %s:%s in SPF record', $context->ip, $mechanism, $cidr)
                    );
                }
            }
        }

        return $this->next($context);
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
}
