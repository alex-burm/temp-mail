<?php

namespace App\Smtp\Service\DkimChecker;

final class DkimContext
{
    public ?array $headers = null;
    public ?string $dkimHeader = null;
    public ?array $params = null;
    public ?string $domain = null;
    public ?string $selector = null;
    public ?string $publicKey = null;

    public ?DkimResultStatus $status = null;
    public ?string $message = null;

    public function __construct(
        public readonly string $rawEmail
    ) {}
}
