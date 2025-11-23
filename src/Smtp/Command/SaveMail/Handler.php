<?php

namespace App\Smtp\Command\SaveMail;

use App\Smtp\Entity\EmailMessage;
use App\Smtp\Repository\EmailMessageRepository;
use App\Smtp\Service\DataParser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Smtp\Command\Checker;

#[AsMessageHandler]
final class Handler
{
    public function __construct(
        protected EmailMessageRepository $repository,
        protected DataParser $dataParser,
        protected MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Command $cmd): void
    {
        $message = new EmailMessage;
        $message->data = $cmd->data;
        $message->recipient = $cmd->recipient;
        $message->ip = $cmd->ip;

        [
            'headers' => $headers,
            'contents' => $contents,
        ] = $this->dataParser->parse($cmd->data);

        $message->headers = $headers;

        $message->html = $this->getContentBody($contents, 'text/html');
        $message->text = $this->getContentBody($contents, 'text/plain');
        $this->repository->save($message);

        $this->messageBus->dispatch(new Checker\Command(
            id: $message->id,
            domain: $cmd->domain,
            ip: $cmd->ip,
        ));
    }

    private function getContentBody(array $contents, string $type): ?string
    {
        return \current(\array_filter($contents, static fn ($x) => $x['type'] === $type))['body'] ?? null;
    }
}
