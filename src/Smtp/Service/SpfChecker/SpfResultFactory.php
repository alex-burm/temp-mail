<?php

namespace App\Smtp\Service\SpfChecker;

readonly class SpfResultFactory
{
    public function __construct(
        private string $domain,
    ) {
    }

    public function make(SpfResultStatus $status, string $message): SpfResult
    {
        return new SpfResult($status, $message, $this->domain);
    }
}
