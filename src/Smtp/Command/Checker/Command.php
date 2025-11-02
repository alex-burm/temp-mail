<?php

namespace App\Smtp\Command\Checked;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Uid\Uuid;

#[AsMessage('async')]
final class Command
{
    public function __construct(
        public readonly Uuid $id,
        public readonly string $domain,
        public readonly string $ip,
    ) {}
}
