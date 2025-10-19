<?php

namespace App\Smtp\Service\SpfChecker;

readonly class SpfResultFactory
{
    public function __construct(
        private string $domain,
    ) {
    }

    public function make(string $status, string $message): SpfResult
    {
        return new SpfResult(SpfResultStatus::from($status), $message, $this->domain);
    }
}
