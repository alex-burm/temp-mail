<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimChecker;
use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;

interface DkimHandlerInterface
{
    public function __construct(DkimChecker $checker);
    public function handle(DkimContext $context): ?DkimResult;
    public function setNext(?DkimHandlerInterface $next): void;
}
