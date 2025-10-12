<?php

namespace App\Smtp\Service\SpfChecker;

enum SpfResultStatus: string
{
    case PASS = 'pass';
    case FAIL = 'fail';
    case SOFTFAIL = 'softfail';
    case NEUTRAL = 'neutral';
    case NONE = 'none';
    case TEMPERROR = 'temperror';
    case PERMERROR = 'permerror';
}
