<?php

namespace App\Smtp\Service;

class EmailAddressGenerator
{
    public function generate(): string
    {
        $records = \array_map(\trim(...), \explode(',', $_ENV['ALLOW_RECORDS'] ?? ''));
        return 'temp' . \random_int(1000, 9999) . '@' . $records[\array_rand($records)];
    }
}

