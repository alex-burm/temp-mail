<?php

namespace App\Smtp\Service\SpfChecker;

use App\Smtp\Service\DnsResolver;
use App\Smtp\Service\SpfChecker\ChainHandler\DepthHandler;
use App\Smtp\Service\SpfChecker\ChainHandler\IncludeHandler;
use App\Smtp\Service\SpfChecker\ChainHandler\IpMatchHandler;
use App\Smtp\Service\SpfChecker\ChainHandler\NeutralFallbackHandler;
use App\Smtp\Service\SpfChecker\ChainHandler\QualifierHandler;
use App\Smtp\Service\SpfChecker\ChainHandler\RecordHandler;

class SpfChecker
{
    private array $handlers = [
        DepthHandler::class,
        RecordHandler::class,
        IpMatchHandler::class,
        IncludeHandler::class,
        QualifierHandler::class,
        NeutralFallbackHandler::class,
    ];

    public function __construct(
        public DnsResolver $dnsResolver,
    ) {
    }

    public function check(string $ip, string $domain, int $depth = 0): SpfResult
    {
        $last = null;
        $chain = null;
        foreach ($this->handlers as $handlerClass) {
            $handler = new $handlerClass($this);

            if (\is_null($chain)) {
                $chain = $handler;
            } else {
                $last->setNext($handler);
            }

            $last = $handler;
        }

        $context = new SpfContext($ip, $domain, $depth);
        return $chain->handle($context);
    }
}
