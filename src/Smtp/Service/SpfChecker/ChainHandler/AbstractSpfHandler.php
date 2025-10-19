<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfChecker;
use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;

abstract class AbstractSpfHandler implements SpfHandlerInterface
{
    protected ?SpfHandlerInterface $next = null;

    public function __construct(
        protected SpfChecker $checker,
    ) {
    }

    public function setNext(?SpfHandlerInterface $next): void
    {
        $this->next = $next;
    }

    protected function next(SpfContext $context): ?SpfResult
    {
        return $this->next?->handle($context);
    }
}
