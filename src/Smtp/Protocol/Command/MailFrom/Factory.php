<?php

namespace App\Smtp\Protocol\Command\MailFrom;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Command\SmtpCommandFactoryInterface;
use App\Smtp\Protocol\Context\SmtpContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.smtp.factory')]
class Factory implements SmtpCommandFactoryInterface
{
    public function match(SmtpContext $context, string $line): ?ProtocolCommand
    {
//        if ($context->state !== SmtpState::READY) {
//            return null;
//        }
        if (preg_match('/^MAIL FROM:\s*<(.+)>/i', $line, $match)) {
            return new Command($match[1]);
        }
        return null;
    }
}
