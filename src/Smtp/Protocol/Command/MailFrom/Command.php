<?php

namespace App\Smtp\Protocol\Command\MailFrom;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;

final class Command implements ProtocolCommand
{
    private string $address;

    public function __construct(string $address)
    {
        $this->address = \trim($address);
    }

    public function execute(SmtpContext $context): ?SmtpResponse
    {
        if ($context->state !== SmtpState::READY) {
            return new SmtpResponse(503, 'Bad sequence of commands' . __CLASS__);
        }

        $context->setFrom($this->address);
        $context->state = SmtpState::MAIL;

        return SmtpResponse::ok('Sender OK');
    }
}
