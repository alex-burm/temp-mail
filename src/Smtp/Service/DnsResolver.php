<?php

namespace App\Smtp\Service;

class DnsResolver
{
    public function getRecords(string $host, int $type = \DNS_ANY): array|false
    {
        return \dns_get_record($host, $type);
    }
}

