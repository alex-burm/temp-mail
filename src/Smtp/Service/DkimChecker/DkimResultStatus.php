<?php

namespace App\Smtp\Service\DkimChecker;

/**
 * https://datatracker.ietf.org/doc/html/rfc7601#section-2.7.1
 */
enum DkimResultStatus: string
{
    case NONE = 'none';
    case PASS = 'pass';
    case FAIL = 'fail';
    case POLICY = 'policy';
    case NEUTRAL = 'neutral';
    case TEMPERROR = 'temperror';
    case PERMERROR = 'permerror';
}
