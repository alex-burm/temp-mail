<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultFactory;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

class QualifierHandler extends AbstractSpfHandler
{
    public function handle(SpfContext $context): ?SpfResult
    {
        if (false === \preg_match('/([~\-+?])?all/', $context->record, $match)) {
            return $this->next($context);
        }

        $qualifier = $match[1] ?? '+';
        $factory = new SpfResultFactory($context->domain);
        return match ($qualifier) {
            '+', '' => $factory->make(SpfResultStatus::PASS, \sprintf('SPF record allows all (+all) for %s', $context->domain)),
            '-'     => $factory->make(SpfResultStatus::FAIL, \sprintf('SPF record forbids this IP (-all) for %s', $context->domain)),
            '~'     => $factory->make(SpfResultStatus::SOFTFAIL, \sprintf('SPF record soft-fails (~all) for %s', $context->domain)),
            '?'     => $factory->make(SpfResultStatus::NEUTRAL, \sprintf('SPF record is neutral (?all) for %s', $context->domain)),
        };
    }
}
