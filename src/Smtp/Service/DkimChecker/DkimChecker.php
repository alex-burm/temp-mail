<?php

namespace App\Smtp\Service\DkimChecker;

use App\Smtp\Service\DkimChecker\ChainHandler\ExtractHeadersHandler;
use App\Smtp\Service\DkimChecker\ChainHandler\FetchKeyHandler;
use App\Smtp\Service\DkimChecker\ChainHandler\FindDkimHeaderHandler;
use App\Smtp\Service\DkimChecker\ChainHandler\ParseParamsHandler;
use App\Smtp\Service\DkimChecker\ChainHandler\VerifySignatureHandler;
use App\Smtp\Service\DnsResolver;

class DkimChecker
{
    private array $handlers = [
        ExtractHeadersHandler::class,
        FindDkimHeaderHandler::class,
        ParseParamsHandler::class,
        FetchKeyHandler::class,
        VerifySignatureHandler::class,
    ];

    public function __construct(
        public readonly DnsResolver $dnsResolver,
    ) {
    }

    public function check(string $rawEmail): DkimResult
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

        $context = new DkimContext($rawEmail);
        return $chain->handle($context);
    }
}
