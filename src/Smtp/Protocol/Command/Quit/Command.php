<?php

namespace App\Smtp\Protocol\Command\Quit;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;

final class Command implements ProtocolCommand
{
    public function execute(SmtpContext $context): ?SmtpResponse
    {
        $context->state = SmtpState::QUIT;

        return new SmtpResponse(221, 'Bye');
    }
}
