<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultFactory;
use App\Smtp\Service\DkimChecker\DkimResultStatus;

final class FetchKeyHandler extends AbstractDkimHandler
{
    use DkimParamParserTrait;

    public function handle(DkimContext $context): ?DkimResult
    {
        $factory = new DkimResultFactory($context->domain);

        $dnsName = \sprintf('%s._domainkey.%s', $context->selector, $context->domain);
        $records = $this->checker->dnsResolver->getRecords($dnsName, \DNS_TXT);

        if (false === $records) {
            return $factory->tempError(
                \sprintf('DNS lookup failed for %s', $dnsName)
            );
        }

        if (\count($records) === 0) {
            return $factory->permError(
                \sprintf('No DKIM record found for %s', $dnsName),
            );
        }

        $record = $this->findDkimDnsRecord($records);
        if (\is_null($record)) {
            return $factory->permError(
                'Invalid or missing DKIM TXT record',
            );
        }
        $recordParams = $this->parse($record);
        if (($recordParams['v'] ?? '') !== 'DKIM1') {
            return $factory->permError(
                'DNS DKIM record missing v=DKIM1',
            );
        }

        $publicKey = $recordParams['p'] ?? '';
        if ($publicKey === '') {
            return $factory->permError(
                'Public key (p=) missing or empty',
            );
        }

        $context->publicKey = $publicKey;
        return $this->next($context);
    }

    private function findDkimDnsRecord(array $records): ?string
    {
        foreach ($records as $record) {
            $txt = $record['txt'] ?? null;
            if ($txt && \stripos($txt, 'v=DKIM1') === 0) {
                return $txt;
            }
        }
        return null;
    }
}
