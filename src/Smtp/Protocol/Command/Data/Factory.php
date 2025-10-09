<?php

namespace App\Smtp\Protocol\Command\Data;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Command\SmtpCommandFactoryInterface;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\State\SmtpState;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.smtp.factory')]
class Factory implements SmtpCommandFactoryInterface
{
    public function match(SmtpContext $context, string $line): ?ProtocolCommand
    {
        $clean = strtoupper(trim($line));

        // DATA → StartCommand
        if ($clean === 'DATA' && $context->state === SmtpState::RCPT) {
            return new StartCommand();
        }

        if ($context->state === SmtpState::DATA && trim($line) === '.') {
            return new EndCommand();
        }

        if ($context->state === SmtpState::DATA) {
            return new ContentCommand($line);
        }
        return null;
    }
}
