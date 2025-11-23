<?php

namespace App\Smtp\Twig\Component;

use App\Smtp\Entity\EmailMessage;
use App\Smtp\Query\GetMessage;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class MessageView
{
    use DefaultActionTrait;
    use HandleTrait;

    public ?string $id = null;
    public ?EmailMessage $message = null;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function mount(?string $id = null): void
    {
        if (false === \is_null($id)) {
            $this->message = $this->handle(new GetMessage\Query($id));
        }
    }

    #[LiveAction]
    public function load(
        #[LiveArg] string $id
    ): void {
        $this->message = $this->handle(new GetMessage\Query($id));
    }
}
