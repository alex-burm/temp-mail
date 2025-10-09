<?php

namespace App\Smtp\Protocol\Context;

use App\Smtp\Protocol\State\SmtpState;

class SmtpContext
{
    public SmtpState $state = SmtpState::GREETING;

    private ?string $helo = null;
    private ?string $from = null;
    private array $recipients = [];
    private string $data = '';

    public function getHelo(): ?string
    {
        return $this->helo;
    }

    public function setHelo(string $domain): void
    {
        $this->helo = $domain;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(string $address): void
    {
        $this->from = $address;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function addRecipient(string $address): void
    {
        $this->recipients[] = $address;
    }

    public function appendData(string $chunk): void
    {
        $this->data .= $chunk . "\n";
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function reset(): void
    {
        $this->from = null;
        $this->recipients = [];
        $this->data = '';
    }
}
