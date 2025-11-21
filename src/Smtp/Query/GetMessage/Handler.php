<?php

namespace App\Smtp\Query\GetMessage;

use App\Smtp\Entity\EmailMessage;
use App\Smtp\Repository\EmailMessageRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
readonly class Handler
{
    public function __construct(
        private EmailMessageRepository $repository,
    ) {
    }

    public function __invoke(Query $query): EmailMessage
    {
        return $this->repository->findById(Uuid::fromString($query->id));
    }
}
