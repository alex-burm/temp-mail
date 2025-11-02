<?php

namespace App\Smtp\Entity;

use App\Smtp\Service\DkimChecker\DkimChecker;
use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DmarcChecker\DmarcResult;
use App\Smtp\Service\SpfChecker\SpfResult;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[Entity]
class EmailMessage
{
    #[Id]
    #[Column(type: UuidType::NAME)]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $id;

    #[Column]
    public ?string $ip = null;

    #[Column]
    public ?string $recipient = null;

    #[Column]
    public ?string $data = null;

    #[Column]
    public array $headers = [];

    #[Column]
    public ?string $html = null;

    #[Column]
    public ?string $text = null;

    #[Column(type: 'spf_result')]
    public ?SpfResult $spf = null;

    #[Column(type: 'dkim_result')]
    public ?DkimResult $dkim = null;

    #[Column(type: 'dmarc_result')]
    public ?DmarcResult $dmarc = null;

    #[Column]
    public ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable;
    }
}
