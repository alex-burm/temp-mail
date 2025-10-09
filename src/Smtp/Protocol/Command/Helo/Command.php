<?php

namespace App\Smtp\Protocol\Command\Helo;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;

class Command implements ProtocolCommand
{
    private string $clientName;

    public function __construct(string $clientName)
    {
        $this->clientName = \trim($clientName);
    }

    public function execute(SmtpContext $context): ?SmtpResponse
    {
        $context->state = SmtpState::READY;
        $context->setHelo($this->clientName);
        return SmtpResponse::ok(\sprintf('Hello %s', $this->clientName));
    }
}
