<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;

final class ExtractHeadersHandler extends AbstractDkimHandler
{
    public function handle(DkimContext $context): ?DkimResult
    {
        $parts = \preg_split("/\r?\n\r?\n/", $context->rawEmail, 2);
        $headerPart = $parts[0] ?? '';

        $unfolded = \preg_replace("/\r?\n[ \t]+/", ' ', $headerPart);
        $context->headers = \explode("\r\n", $unfolded);
        return $this->next($context);
    }
}
