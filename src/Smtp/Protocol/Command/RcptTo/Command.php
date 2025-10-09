<?php

namespace App\Smtp\Protocol\Command\RcptTo;

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
        if (false === in_array($context->state, [SmtpState::MAIL, SmtpState::RCPT], true)) {
            return new SmtpResponse(503, 'Bad sequence of commands' . __CLASS__);
        }

        $context->state = SmtpState::RCPT;
        $context->addRecipient($this->address);
        return SmtpResponse::ok('Recipient OK');
    }
}
