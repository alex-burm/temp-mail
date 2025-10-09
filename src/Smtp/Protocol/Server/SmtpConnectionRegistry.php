<?php

namespace App\Smtp\Protocol\Server;

use App\Smtp\Protocol\Context\SmtpContext;

final class SmtpConnectionRegistry
{
    private array $idToContext = [];

    private \SplObjectStorage $contextToId;

    public function __construct()
    {
        $this->contextToId = new \SplObjectStorage();
    }

    public function attach(int $connectionId, SmtpContext $context): void
    {
        $this->idToContext[$connectionId] = $context;
        $this->contextToId[$context] = $connectionId;
    }

    public function getContext(int $connectionId): ?SmtpContext
    {
        return $this->idToContext[$connectionId] ?? null;
    }

    public function getId(SmtpContext $context): ?int
    {
        return $this->contextToId->contains($context)
            ? $this->contextToId[$context]
            : null;
    }

    public function detachById(int $connectionId): void
    {
        if (\array_key_exists($connectionId, $this->idToContext)) {
            $context = $this->idToContext[$connectionId];
            unset($this->idToContext[$connectionId]);
            $this->contextToId->detach($context);
        }
    }

    public function detachByContext(SmtpContext $context): void
    {
        if ($this->contextToId->contains($context)) {
            $connectionId = $this->contextToId[$context];
            unset($this->idToContext[$connectionId]);
            $this->contextToId->detach($context);
        }
    }
}
