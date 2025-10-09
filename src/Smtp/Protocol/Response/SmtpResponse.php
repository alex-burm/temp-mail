<?php

namespace App\Smtp\Protocol\Response;

final class SmtpResponse
{
    public function __construct(
        public readonly int $code,
        public readonly string $message
    ) {}

    public function __toString(): string
    {
        return \sprintf("%d %s\r\n", $this->code, $this->message);
    }

    public static function ok(string $message = 'OK'): self
    {
        return new self(250, $message);
    }

    public static function error(string $message = 'Error'): self
    {
        return new self(500, $message);
    }

    public static function ready(string $message = 'SMTP Service ready'): self
    {
        return new self(220, $message);
    }
}
