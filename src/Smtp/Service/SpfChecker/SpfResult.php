<?php

namespace App\Smtp\Service\SpfChecker;

readonly class SpfResult
{
    public function __construct(
        public SpfResultStatus $status,
        public ?string $message = null
    ) {}
}
