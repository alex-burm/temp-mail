<?php

namespace App\Smtp\Protocol\Command\Data;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;

final class StartCommand implements ProtocolCommand
{
    public function execute(SmtpContext $context): ?SmtpResponse
    {
        if (false === \in_array($context->state, [SmtpState::RCPT, SmtpState::DATA], true)) {
            return new SmtpResponse(503, 'Bad sequence of commands' . __CLASS__);
        }

        $context->state = SmtpState::DATA;
        return new SmtpResponse(354, 'End data with <CR><LF>.<CR><LF>');
    }
}
