<?php

namespace App\Smtp\Protocol\Command\RcptTo;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;
use App\Smtp\Repository\EmailAddressRepository;

final class Command implements ProtocolCommand
{
    public function __construct(
        private string $address,
        private EmailAddressRepository $repository,
    ) {
        $this->address = \trim($address);
    }

    public function execute(SmtpContext $context): ?SmtpResponse
    {
        if (false === \in_array($context->state, [SmtpState::MAIL, SmtpState::RCPT], true)) {
            return new SmtpResponse(503, 'Bad sequence of commands' . __CLASS__);
        }

        $record = $this->repository->findByAddr($this->address);
        if (\is_null($record)) {
            return SmtpResponse::error('5.4.1 Recipient address rejected: Access denied');
        }

        $context->state = SmtpState::RCPT;
        $context->addRecipient($this->address);
        return SmtpResponse::ok('Recipient OK');
    }
}
