<?php

namespace App\Smtp\Twig\Component;

use App\Smtp\Command\CreateMailbox;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class EmailAddress
{
    use DefaultActionTrait;
    use HandleTrait;

    public string $email = '';

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[LiveAction]
    public function refresh(): void
    {
        $this->email = $this->generateEmail();
    }

    private function generateEmail(): string
    {
        return $this->handle(new CreateMailbox\Command());
    }
}
