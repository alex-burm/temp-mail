<?php

namespace App\Smtp\Command\SaveMail;

final class Command
{
    public function __construct(
        public readonly string $from,
        public readonly array $recipients,
        public readonly string $data
    ) {}
}
