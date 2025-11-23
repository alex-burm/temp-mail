<?php

namespace App\Smtp\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class EmailAddress
{
    #[Id]
    #[GeneratedValue]
    #[Column]
    public int $id;

    #[Column]
    public ?string $value = null;

    #[Column]
    public ?\DateTimeImmutable $createdAt = null;

    #[Column]
    public ?\DateTimeImmutable $expiredAt = null;

    public function __construct(string $addr, int $ttl)
    {
        $this->value = $addr;
        $this->createdAt = new \DateTimeImmutable;
        $this->expiredAt = $this->createdAt->modify(\sprintf('+%d hours', $ttl));
    }
}
