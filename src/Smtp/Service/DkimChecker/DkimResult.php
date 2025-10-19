<?php

namespace App\Smtp\Service\DkimChecker;

readonly class DkimResult
{
    public function __construct(
        public DkimResultStatus $status,
        public ?string $message = null,
        public ?string $domain = null,
    ) {
    }
}
