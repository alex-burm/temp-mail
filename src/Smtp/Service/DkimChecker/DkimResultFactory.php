<?php

namespace App\Smtp\Service\DkimChecker;

readonly class DkimResultFactory
{
    public function __construct(
        private ?string $domain = null,
    ) {
    }

    public function make(DkimResultStatus $status, string $message): DkimResult
    {
        return new DkimResult($status, $message, $this->domain);
    }

    public function tempError(string $message): DkimResult
    {
        return $this->make(DkimResultStatus::TEMPERROR, $message);
    }

    public function permError(string $message): DkimResult
    {
        return $this->make(DkimResultStatus::PERMERROR, $message);
    }
}
