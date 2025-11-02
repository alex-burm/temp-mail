<?php

namespace App\Shared\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Statement;
use Doctrine\Persistence\ManagerRegistry;

abstract class AbstractRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, $this->getMetaClass());
    }

    abstract protected function getMetaClass(): string;

    protected function prepare(string $sql): Statement
    {
        return $this->getEntityManager()->getConnection()->prepare($sql);
    }
}
