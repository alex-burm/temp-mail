<?php

namespace App\Smtp\Protocol\Command\Quit;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Command\SmtpCommandFactoryInterface;
use App\Smtp\Protocol\Context\SmtpContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.smtp.factory')]
class Factory implements SmtpCommandFactoryInterface
{
    public function match(SmtpContext $context, string $line): ?ProtocolCommand
    {
        if (strtoupper(trim($line)) === 'QUIT') {
            return new Command();
        }
        return null;
    }
}
