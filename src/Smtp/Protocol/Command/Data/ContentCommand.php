<?php

namespace App\Smtp\Protocol\Command\Data;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use App\Smtp\Protocol\State\SmtpState;

final class ContentCommand implements ProtocolCommand
{
    public function __construct(
        private string $line
    ) {
    }

    public function execute(SmtpContext $context): ?SmtpResponse
    {
        if ($context->state !== SmtpState::DATA) {
            return new SmtpResponse(503, 'Bad sequence of commands');
        }

        // строки с ".." превратить в одну точку
        $line = str_starts_with($this->line, '..')
            ? substr($this->line, 1)
            : $this->line;

        $context->appendData($line);

        return null;
    }
}
