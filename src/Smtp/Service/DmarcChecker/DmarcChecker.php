<?php

namespace App\Smtp\Service\DmarcChecker;

use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultStatus;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

class DmarcChecker
{
    public function check(string $domain, SpfResult $spf, DkimResult $dkim): DmarcResult
    {
        $record = $this->getDmarcRecord($domain);

        if ($record === null) {
            return new DmarcResult(DmarcResultStatus::NONE, 'No DMARC record found');
        }

        $params = $this->parseDmarcParams($record);

        $dkimAligned = $dkim->status === DkimResultStatus::PASS
            && $this->isAligned($dkim->domain, $domain, $params['adkim'] ?? 'r');

        $spfAligned = $spf->status === SpfResultStatus::PASS
            && $this->isAligned($spf->domain, $domain, $params['aspf'] ?? 'r');

        $passed = $dkimAligned || $spfAligned;

        if ($passed) {
            return new DmarcResult(DmarcResultStatus::PASS, 'DMARC passed');
        }

        $policy = $params['p'] ?? 'none';
        return match ($policy) {
            'reject' => new DmarcResult(DmarcResultStatus::REJECT, 'DMARC failed, message rejected'),
            'quarantine' => new DmarcResult(DmarcResultStatus::QUARANTINE, 'DMARC failed, message quarantined'),
            default => new DmarcResult(DmarcResultStatus::NONE, 'DMARC failed, but policy is none'),
        };
    }

    protected function getDnsRecords(string $host, int $type): array|false
    {
        return \dns_get_record($host, $type);
    }

    private function getDmarcRecord(string $domain): ?string
    {
        $records = $this->getDnsRecords('_dmarc.' . $domain, DNS_TXT);
        foreach ($records as $r) {
            if (isset($r['txt']) && \str_starts_with($r['txt'], 'v=DMARC1')) {
                return $r['txt'];
            }
        }
        return null;
    }

    private function isAligned(string $sub, string $root, string $mode): bool
    {
        return 's' === $mode
            ? \strcasecmp($sub, $root) === 0
            : \str_ends_with($sub, '.' . $root) || \strcasecmp($sub, $root) === 0;
    }

    private function parseDmarcParams(string $record): array
    {
        $parts = \explode(';', $record);
        $params = [];
        foreach ($parts as $part) {
            [$k, $v] = \array_map(\trim(...), \explode('=', $part) + [null, null]);
            if ($k && $v) {
                $params[$k] = $v;
            }
        }
        return $params;
    }
}

