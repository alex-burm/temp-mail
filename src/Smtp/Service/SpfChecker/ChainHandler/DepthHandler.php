<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultFactory;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

final class DepthHandler extends AbstractSpfHandler
{
    private const MAX_DNS_LOOKUPS = 10;

    public function handle(SpfContext $context): ?SpfResult
    {
        if ($context->depth > self::MAX_DNS_LOOKUPS) {
            return new SpfResultFactory($context->domain)
                ->make(SpfResultStatus::PERMERROR, 'Too many DNS lookups');
        }
        return $this->next($context);
    }
}
