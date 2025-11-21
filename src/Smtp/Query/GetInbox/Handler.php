<?php

namespace App\Smtp\Query\GetInbox;

use App\Smtp\Repository\EmailMessageRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class Handler
{
    public function __construct(
        private EmailMessageRepository $repository,
    ) {
    }

    public function __invoke(Query $query): array
    {
        return $this->repository->getList($query->emailAddress);
    }
}
