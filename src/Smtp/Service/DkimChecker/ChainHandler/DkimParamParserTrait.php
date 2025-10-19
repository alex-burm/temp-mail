<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

trait DkimParamParserTrait
{
    protected function parse(string $header): array
    {
        $input = \preg_replace("/\r?\n[ \t]*/", '', $header);
        $params = [];
        foreach (\explode(';', $input) as $chunk) {
            $chunk = \trim($chunk);
            if (false === \str_contains($chunk, '=')) {
                continue;
            }
            [$key, $value] = \array_map(trim(...), \explode('=', $chunk, 2));
            $params[\strtolower($key)] = $value;
        }
        return $params;
    }
}

