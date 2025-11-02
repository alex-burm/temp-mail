<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultFactory;
use App\Smtp\Service\DkimChecker\DkimResultStatus;

final class FindDkimHeaderHandler extends AbstractDkimHandler
{
    public function handle(DkimContext $context): ?DkimResult
    {
        foreach ($context->headers as $header) {
            if (\stripos($header, 'DKIM-Signature:') === 0) {
                $value = \substr($header, strlen('DKIM-Signature:'));
                $context->dkimHeader = \trim(\preg_replace('/\r?\n[ \t]+/', ' ', $value));
                return $this->next($context);
            }
        }

        return (new DkimResultFactory($context->domain))->make(
            DkimResultStatus::NONE,
            'DKIM-Signature header not found'
        );
    }
}
