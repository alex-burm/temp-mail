<?php

namespace App\Smtp\Repository;

use App\Shared\Repository\AbstractRepository;
use App\Smtp\Entity\EmailMessage;

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
}

