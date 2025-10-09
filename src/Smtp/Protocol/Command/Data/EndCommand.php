<?php

namespace App\Smtp\Protocol\Command\Data;

use App\Smtp\Command\SaveMail\Command;
use App\Smtp\Command\SaveMail\SaveMailHandler;
use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;

final class EndCommand implements ProtocolCommand
{
    public function execute(SmtpContext $context): ?SmtpResponse
    {
        if ($context->state !== SmtpState::DATA) {
            return new SmtpResponse(503, 'Bad sequence of commands');
        }

        $handler = new SaveMailHandler();
        $handler(new Command(
            from: $context->getFrom(),
            recipients: $context->getRecipients(),
            data: $context->getData(),
        ));

        $context->reset();
        $context->state = SmtpState::READY;

        return new SmtpResponse(250, 'Message accepted for delivery');
    }
}
