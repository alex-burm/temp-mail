<?php

namespace App\Smtp\Service\DmarcChecker;

readonly class DmarcResult
{
    public function __construct(
        public DmarcResultStatus $status,
        public ?string $message = null,
    ) {
    }
}
