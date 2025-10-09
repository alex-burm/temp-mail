<?php

namespace App\Smtp\Protocol\Parser;

use App\Smtp\Protocol\Command\ProtocolCommand;
use App\Smtp\Protocol\Context\SmtpContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SmtpCommandResolver
{
    public function __construct(
        #[AutowireIterator('app.smtp.factory')]
        private iterable $factories
    ) {
    }

    public function resolve(SmtpContext $context, string $line): ProtocolCommand
    {
        foreach ($this->factories as $factory) {
            $cmd = $factory->match($context, $line);
            if ($cmd instanceof ProtocolCommand) {
                return $cmd;
            }
        }

        throw new \RuntimeException(\sprintf('Unknown command: %s', $line));
    }
}
