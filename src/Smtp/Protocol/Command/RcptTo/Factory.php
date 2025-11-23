<?php

namespace App\Smtp\Protocol\Command\RcptTo;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Command\SmtpCommandFactoryInterface;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Repository\EmailAddressRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.smtp.factory')]
class Factory implements SmtpCommandFactoryInterface
{
    public function __construct(
        private EmailAddressRepository $addressRepository,
    ) {
    }

    public function match(SmtpContext $context, string $line): ?ProtocolCommand
    {
        if (preg_match('/^RCPT TO:\s*<(.+)>/i', $line, $match)) {
            return new Command($match[1], $this->addressRepository);
        }
        return null;
    }
}
