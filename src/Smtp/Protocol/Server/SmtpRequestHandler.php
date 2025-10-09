<?php

namespace App\Smtp\Protocol\Server;

use App\Smtp\Protocol\Parser\SmtpCommandResolver;
use App\Smtp\Protocol\State\SmtpState;
use Swoole\Server;

final class SmtpRequestHandler
{
    public function __construct(
        private SmtpCommandResolver    $resolver,
        private SmtpConnectionRegistry $registry,
    ) {}

    public function handle(Server $server, int $connectionId, string $data): void
    {
        $context = $this->registry->getContext($connectionId);

        foreach (\preg_split("/\r\n/", $data) as $line) {
            if ($line === '') {
                continue;
            }

            $command  = $this->resolver->resolve($context, $line);
            $response = $command->execute($context);

            if ($response) {
                $server->send($connectionId, (string)$response);
            }

            if ($context->state === SmtpState::QUIT) {
                $server->close($connectionId);
                return;
            }
        }
    }
}
