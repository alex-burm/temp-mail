<?php

namespace App\Smtp\Command\Checker;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Uid\Uuid;

#[AsMessage('async')]
final readonly class Command
{
    public function __construct(
        public Uuid $id,
        public string $domain,
        public string $ip,
    ) {}
}
