<?php

namespace App\Smtp\Service\SpfChecker\ChainHandler;

use App\Smtp\Service\SpfChecker\SpfChecker;
use App\Smtp\Service\SpfChecker\SpfContext;
use App\Smtp\Service\SpfChecker\SpfResult;

interface SpfHandlerInterface
{
    public function __construct(SpfChecker $checker);
    public function handle(SpfContext $context): ?SpfResult;
    public function setNext(?SpfHandlerInterface $next): void;
}
