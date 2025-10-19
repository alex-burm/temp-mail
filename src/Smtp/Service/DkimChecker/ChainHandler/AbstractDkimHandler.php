<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimChecker;
use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;

abstract class AbstractDkimHandler implements DkimHandlerInterface
{
    protected ?DkimHandlerInterface $next = null;

    public function __construct(
        protected DkimChecker $checker,
    ) {
    }

    public function setNext(?DkimHandlerInterface $next): void
    {
        $this->next = $next;
    }

    protected function next(DkimContext $context): ?DkimResult
    {
        return $this->next?->handle($context);
    }
}
