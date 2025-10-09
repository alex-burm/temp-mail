<?php

use Swoole\Server;

$storeDir = __DIR__ . '/mail';
@mkdir($storeDir, 0755, true);

$server = new Server("0.0.0.0", 25);

$server->set([
    'worker_num' => 2,
    'daemonize' => false,
]);

$connections = [];

$server->on("connect", function (Server $serv, int $fd) use (&$connections) {
    $connections[$fd] = [
        'helo' => null,
        'mail_from' => null,
        'rcpt_to' => [],
        'data_mode' => false,
        'data' => ''
    ];
    $serv->send($fd, "220 php-smtp Swoole ESMTP ready\r\n");
    echo "Connected\n";
});

$server->on("receive", function (Server $serv, int $fd, int $rid, string $data) use (&$connections, $storeDir) {
    $lines = preg_split("/\r\n|\n/", trim($data));

    foreach ($lines as $line) {
        echo $line . "\n";
        $state =& $connections[$fd];

        if ($state['data_mode']) {
            if ($line === ".") {
                $state['data_mode'] = false;
                storeMessage($state, $storeDir);
                $serv->send($fd, "250 OK queued\r\n");

                $state['mail_from'] = null;
                $state['rcpt_to'] = [];
                $state['data'] = '';
            } else {
                if (strpos($line, '..') === 0) {
                    $line = substr($line, 1);
                }
                $state['data'] .= $line . "\r\n";
            }
            continue;
        }

        $lineUp = strtoupper($line);

        if (strpos($lineUp, 'EHLO') === 0 || strpos($lineUp, 'HELO') === 0) {
            $parts = explode(' ', $line, 2);
            $state['helo'] = $parts[1] ?? '';
            $serv->send($fd, "250-Hello {$state['helo']}\r\n250 OK\r\n");

        } elseif (strpos($lineUp, 'MAIL FROM:') === 0) {
            if (preg_match('/^MAIL FROM:\s*<(.*)>/i', $line, $m)) {
                $state['mail_from'] = $m[1];
                $serv->send($fd, "250 OK\r\n");
            } else {
                $serv->send($fd, "501 Syntax error in MAIL FROM\r\n");
            }

        } elseif (strpos($lineUp, 'RCPT TO:') === 0) {
            if (preg_match('/^RCPT TO:\s*<(.*)>/i', $line, $m)) {
                $state['rcpt_to'][] = strtolower($m[1]);
                $serv->send($fd, "250 OK\r\n");
            } else {
                $serv->send($fd, "501 Syntax error in RCPT TO\r\n");
            }

        } elseif ($lineUp === 'DATA') {
            if (!$state['mail_from'] || empty($state['rcpt_to'])) {
                $serv->send($fd, "503 Bad sequence of commands\r\n");
            } else {
                $state['data_mode'] = true;
                $state['data'] = '';
                $serv->send($fd, "354 End data with <CR><LF>.<CR><LF>\r\n");
            }

        } elseif ($lineUp === 'RSET') {
            $state['mail_from'] = null;
            $state['rcpt_to'] = [];
            $state['data'] = '';
            $state['data_mode'] = false;
            $serv->send($fd, "250 OK\r\n");

        } elseif ($lineUp === 'QUIT') {
            $serv->send($fd, "221 Bye\r\n");
            $serv->close($fd);

        } else {
            $serv->send($fd, "502 Command not implemented\r\n");
        }
    }
});

$server->on("close", function (Server $serv, int $fd) use (&$connections) {
    unset($connections[$fd]);
});

$server->start();

function storeMessage(array $state, string $dir): void
{
    $ts = time();
    foreach ($state['rcpt_to'] as $rcpt) {
        $safe = preg_replace('/[^a-z0-9@._+-]/i', '_', $rcpt);
        $folder = "$dir/$safe";
        @mkdir($folder, 0755, true);

        $id = uniqid($ts);
        $path = $folder . '/' . $id . '.eml';
        file_put_contents($path, $state['data']);

        $meta = [
            'from' => $state['mail_from'],
            'to' => $state['rcpt_to'],
            'helo' => $state['helo'],
            'saved_at' => date('c', $ts),
            'file' => $path
        ];
        file_put_contents($folder . '/' . $id . '.json', json_encode($meta, JSON_PRETTY_PRINT));
    }
}
