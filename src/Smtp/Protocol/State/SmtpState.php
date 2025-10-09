<?php

namespace App\Smtp\Protocol\State;

enum SmtpState: string
{
    case GREETING = 'greeting';
    case READY = 'ready';
    case MAIL = 'mail';
    case RCPT = 'rcpt';
    case DATA = 'data';
    case QUIT = 'quit';
}
