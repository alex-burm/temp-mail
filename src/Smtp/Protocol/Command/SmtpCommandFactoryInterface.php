<?php

namespace App\Smtp\Protocol\Command;

use App\Smtp\Protocol\Context\SmtpContext;

interface SmtpCommandFactoryInterface
{
    public function match(SmtpContext $context, string $line): ?ProtocolCommand;
}
