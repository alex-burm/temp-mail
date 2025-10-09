<?php

namespace App\Smtp\Query\GetMailById;

class GetMailByIdHandler
{
    public function __invoke(GetMailByIdQuery $query): Email
    {
        return new Email();
    }
}
