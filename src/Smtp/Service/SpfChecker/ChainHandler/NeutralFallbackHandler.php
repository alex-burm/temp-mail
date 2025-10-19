<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultFactory;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

final class NeutralFallbackHandler extends AbstractSpfHandler
{
    public function handle(SpfContext $context): ?SpfResult
    {
        return new SpfResultFactory($context->domain)->make(
            SpfResultStatus::NEUTRAL,
            \sprintf('No matching rule found for IP %s in %s', $context->ip, $context->domain),
        );
    }
}
