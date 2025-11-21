<?php

namespace App\Smtp\Command\GenerateEmailAddress;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Handler
{
    public function __invoke(Command $command): string
    {
        $records = \array_map(\trim(...), \explode(',', $_ENV['ALLOW_RECORDS'] ?? ''));
        return 'temp' . \random_int(1000, 9999) . '@' . $records[\array_rand($records)];
    }
}
