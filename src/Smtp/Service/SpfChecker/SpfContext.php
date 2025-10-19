<?php

namespace App\Smtp\Service\SpfChecker;

class SpfContext
{
    public ?string $record = null;

    public function __construct(
        public readonly string $ip,
        public readonly string $domain,
        public int $depth = 0
    ) {}
}

