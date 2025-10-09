<?php

namespace App\Smtp\Protocol\Command;

use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;

interface ProtocolCommand
{
    public function execute(SmtpContext $context): ?SmtpResponse;
}
