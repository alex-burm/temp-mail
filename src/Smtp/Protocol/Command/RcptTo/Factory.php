<?php

namespace App\Smtp\Protocol\Command\RcptTo;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Command\SmtpCommandFactoryInterface;
use App\Smtp\Protocol\Context\SmtpContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.smtp.factory')]
class Factory implements SmtpCommandFactoryInterface
{
    public function match(SmtpContext $context, string $line): ?ProtocolCommand
    {
        if (preg_match('/^RCPT TO:\s*<(.+)>/i', $line, $match)) {
            return new Command($match[1]);
        }
        return null;
    }
}
