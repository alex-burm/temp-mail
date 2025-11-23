<?php

namespace App\Smtp\Repository;

use App\Shared\Repository\AbstractRepository;
use App\Smtp\Entity\EmailAddress;
use Symfony\Component\Uid\Uuid;

final class EmailAddressRepository extends AbstractRepository
{
    protected function getMetaClass(): string
    {
        return EmailAddress::class;
    }

    public function save(EmailAddress $addr): void
    {
        $this->getEntityManager()->persist($addr);
        $this->getEntityManager()->flush();
    }

    public function findByAddr(string $addr): ?EmailAddress
    {
        return $this->findOneBy([
            'value' => $addr,
        ]);
    }
}

