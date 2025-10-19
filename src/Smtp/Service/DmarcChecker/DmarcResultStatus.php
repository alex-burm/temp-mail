<?php

namespace App\Smtp\Service\DmarcChecker;

enum DmarcResultStatus: string
{
    case NONE = 'none';
    case PASS = 'pass';
    case FAIL = 'fail';
    case QUARANTINE = 'quarantine';
    case REJECT = 'reject';
}
