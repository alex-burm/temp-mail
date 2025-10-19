<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultFactory;
use App\Smtp\Service\SpfChecker\SpfResultStatus;

final class IncludeHandler extends AbstractSpfHandler
{
    public function handle(SpfContext $context): ?SpfResult
    {
        if (false === \preg_match_all('/include:([^\s]+)/', $context->record, $matches)) {
            return $this->next($context);
        }

        foreach ($matches[1] as $domain) {
            $result = $this->checker->check($context->ip, $domain, $context->depth + 1);

            if ($result->status === SpfResultStatus::PERMERROR) {
                return $result;
            }

            if ($result->status === SpfResultStatus::PASS) {
                return new SpfResultFactory($domain)->make(
                    SpfResultStatus::PASS,
                    \sprintf('IP %s authorized via include:%s', $context->ip, $domain)
                );
            }
        }

        return $this->next($context);
    }
}
