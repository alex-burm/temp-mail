<?php

namespace App\Smtp\Repository;

use App\Shared\Repository\AbstractRepository;
use App\Smtp\Entity\EmailMessage;
use Symfony\Component\Uid\Uuid;

final class EmailMessageRepository extends AbstractRepository
{
    protected function getMetaClass(): string
    {
        return EmailMessage::class;
    }

    public function save(EmailMessage $message): void
    {
        $this->getEntityManager()->persist($message);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?EmailMessage
    {
        return $this->find($id);
    }

    public function getList(string $emailAddress): array
    {
        return $this->findBy([
            'recipient' => $emailAddress,
        ], [
            'createdAt' => 'desc',
        ]);
    }
}

