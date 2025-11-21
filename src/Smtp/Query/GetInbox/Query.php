<?php

namespace App\Smtp\Query\GetInbox;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage]
readonly class Query
{
    public function __construct(
        public string $emailAddress,
    ) {
    }
}
