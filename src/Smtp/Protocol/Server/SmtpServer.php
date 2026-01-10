<?php

namespace App\Smtp\Protocol\Server;

use App\Smtp\Protocol\Context\SmtpContext;
use App\Smtp\Protocol\Response\SmtpResponse;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;

class SmtpServer
{
    private Server $server;

    public function __construct(
        private SmtpConnectionRegistry $registry,
        private SmtpRequestHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function start(string $host, int $port): void
    {
        $this->server = new Server($host, $port);

        Timer::tick(5000, function () {
            $connection = $this->entityManager->getConnection();
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
            //echo '[' . date('Y-m-d H:i:s') . '] Ping 5 sec' . "\n";

            $this->entityManager->clear();
            \gc_collect_cycles();
        });

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
        $info = $this->server->getClientInfo($connectionId);

        $context = new SmtpContext();
        $context->setIp($info['remote_ip']);

        $this->registry->attach($connectionId, $context);
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
