<?php

namespace App\Smtp\Protocol\Command\Helo;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Command\SmtpCommandFactoryInterface;
use App\Smtp\Protocol\Context\SmtpContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.smtp.factory')]
class Factory implements SmtpCommandFactoryInterface
{
    public function match(SmtpContext $context, string $line): ?ProtocolCommand
    {
        $line = \strtoupper($line);
        if (\str_starts_with($line, 'HELO ') || \str_starts_with($line, 'EHLO ')) {
            return new Command(substr($line, 5));
        }
        return null;
    }
}
