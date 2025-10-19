<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultFactory;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

final class RecordHandler extends AbstractSpfHandler
{
    public function handle(SpfContext $context): ?SpfResult
    {
        $factory = new SpfResultFactory($context->domain);
        $records = $this->checker->dnsResolver->getRecords($context->domain, \DNS_TXT);
        if (false === $records) {
            return $factory->make(
                SpfResultStatus::TEMPERROR,
                \sprintf('DNS lookup failed for %s', $context->domain)
            );
        }

        $spf = \array_values(
            \array_filter(
                $records,
                static fn($r) => isset($r['txt']) && \str_starts_with($r['txt'], 'v=spf1')
            )
        );

        if (\count($spf) === 0) {
            if (\array_any($records, static fn($r) => isset($r['txt']) && \str_starts_with($r['txt'], 'spf1'))) {
                return $factory->make(
                    SpfResultStatus::PERMERROR,
                    \sprintf('Invalid SPF record for %s', $context->domain)
                );
            }
            return $factory->make(
                SpfResultStatus::NONE,
                \sprintf('No SPF record found for %s', $context->domain)
            );
        }

        if (\count($spf) > 1) {
            return $factory->make(
                SpfResultStatus::PERMERROR,
                \sprintf('Multiple SPF records found for %s', $context->domain)
            );
        }

        $context->record = $spf[0]['txt'];
        return $this->next($context);
    }
}
