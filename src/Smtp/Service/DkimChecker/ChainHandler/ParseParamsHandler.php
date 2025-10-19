<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultFactory;
use App\Smtp\Service\DkimChecker\DkimResultStatus;

final class ParseParamsHandler extends AbstractDkimHandler
{
    use DkimParamParserTrait;

    public function handle(DkimContext $context): ?DkimResult
    {
        $factory = new DkimResultFactory();

        $params = $this->parse($context->dkimHeader);

        if (0 === \count($params)) {
            return $factory->make(
                DkimResultStatus::NEUTRAL,
                'Unable to parse DKIM-Signature header'
            );
        }

        foreach (['v', 'a', 'd', 's', 'bh', 'b'] as $k) {
            if (false === \array_key_exists($k, $params)) {
                return $factory->permError(
                    \sprintf('Missing required DKIM parameter: %s', $k)
                );
            }
        }

        if (\strcasecmp($params['v'], '1') !== 0 && \strcasecmp($params['v'], 'DKIM1') !== 0) {
            return $factory->permError(
                'Invalid DKIM version',
            );
        }

        $context->params = $params;
        $context->domain = $context->params['d'];
        $context->selector = $context->params['s'];

        return $this->next($context);
    }
}
