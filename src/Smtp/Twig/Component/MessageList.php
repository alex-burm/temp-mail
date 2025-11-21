<?php

namespace App\Smtp\Twig\Component;

use App\Smtp\Query\GetInbox;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class MessageList
{
    use DefaultActionTrait;
    use HandleTrait;

    public array $messages = [];

    public ?string $id = null;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[LiveAction]
    public function refresh(
        #[LiveArg] string $email
    ): void {
        $this->messages = $this->handle(new GetInbox\Query($email));
    }
}
