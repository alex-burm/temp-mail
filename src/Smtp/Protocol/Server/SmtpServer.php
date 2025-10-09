<?php

namespace App\Smtp\Protocol\Server;

use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Parser\SmtpCommandResolver;
use App\Smtp\Protocol\Response\SmtpResponse;
use Swoole\Process;
use Swoole\Server;

class SmtpServer
{
    private Server $server;

    public function __construct(
        private SmtpCommandResolver    $resolver,
        private SmtpConnectionRegistry $registry,
        private SmtpRequestHandler     $handler,
    ) {
    }

    public function start(string $host, int $port): void
    {
        $this->server = new Server($host, $port);

        $this->server->on('start', fn () => $this->onStart());
        $this->server->on('connect', fn (Server $s, int $cid) => $this->onConnect($cid));
        $this->server->on('receive', fn (Server $s, int $cid, int $rid, string $data) => $this->onReceive($cid, $data));
        $this->server->on('close', fn (Server $srv, int $cid) => $this->onClose($cid));

        $this->server->start();
    }

    public function onStart()
    {
        Process::signal(\SIGINT, function () {
            $this->server->shutdown();
        });
    }

    public function onConnect(int $connectionId) {
        $this->registry->attach($connectionId, new SmtpContext());
        $this->server->send($connectionId, SmtpResponse::ready('SMTP Ready'));
    }

    public function onReceive(int $connectionId, string $data) {
        try {
            $this->handler->handle($this->server, $connectionId, $data);
        } catch (\Throwable $e) {
            $this->server->send($connectionId, SmtpResponse::error($e->getMessage()));
        }
    }

    public function onClose(int $connectionId) {
        $this->registry->detachById($connectionId);
    }
}
