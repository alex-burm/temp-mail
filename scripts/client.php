<?php

$host = $argv[1] ?? '127.0.0.1';
$port = (int)($argv[2] ?? 25);
$timeout = 5.0;

function readResponse($fp): array
{
    $lines = [];
    while (!feof($fp)) {
        $line = fgets($fp);
        if ($line === false) break;
        $lines[] = rtrim($line, "\r\n");
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $lines;
}

function expectCode(array $lines, int $expected): bool
{
    if (empty($lines)) return false;
    $code = (int)substr($lines[0], 0, 3);
    return $code === $expected;
}

function sendLine($fp, string $line): void
{
    fwrite($fp, $line . "\r\n");
    echo "C: $line\n";
}

$remote = "tcp://{$host}:{$port}";
echo "Connecting to {$remote} ...\n";
$fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
if (!$fp) {
    echo "Connection failed: $errstr ($errno)\n";
    exit(1);
}
stream_set_timeout($fp, $timeout);

$greeting = readResponse($fp);
foreach ($greeting as $l) echo "S: $l\n";
if (!expectCode($greeting, 220)) {
    echo "Unexpected greeting\n";
    fclose($fp);
    exit(1);
}

$clientHost = gethostname() ?: 'localhost';
$mailFrom = 'alice@example.com';
$rcptTo = 'bob@example.com';

sendLine($fp, "HELO {$clientHost}");
$resp = readResponse($fp);
foreach ($resp as $l) echo "S: $l\n";
if (!expectCode($resp, 250)) {
    echo "HELO failed\n";
    fclose($fp);
    exit(1);
}

sendLine($fp, "MAIL FROM:<{$mailFrom}>");
$resp = readResponse($fp);
foreach ($resp as $l) echo "S: $l\n";
if (!expectCode($resp, 250)) {
    echo "MAIL FROM failed\n";
    fclose($fp);
    exit(1);
}

sendLine($fp, "RCPT TO:<{$rcptTo}>");
$resp = readResponse($fp);
foreach ($resp as $l) echo "S: $l\n";
if (!expectCode($resp, 250)) {
    echo "RCPT TO failed\n";
    fclose($fp);
    exit(1);
}

sendLine($fp, "DATA");
$resp = readResponse($fp);
foreach ($resp as $l) echo "S: $l\n";
if (!expectCode($resp, 354)) {
    echo "DATA not accepted\n";
    fclose($fp);
    exit(1);
}

$headers = [
    "From: Alice <{$mailFrom}>",
    "To: Bob <{$rcptTo}>",
    "Subject: Test message",
    "Date: " . date('r'),
    "Message-ID: <" . uniqid() . "@example.local>"
];

$bodyLines = [
    "Hello Bob,",
    "",
    "This is a test message sent by a PHP SMTP client.",
    "It contains a dot-starting line below:",
    ".This line starts with a dot and must be dot-stuffed by the client",
    "",
    "Best regards,",
    "PHP SMTP client",
];

foreach ($headers as $h) {
    if (str_starts_with($h, '.')) {
        sendLine($fp, '.' . $h);
    } else {
        sendLine($fp, $h);
    }
}
sendLine($fp, '');

foreach ($bodyLines as $line) {
    if (str_starts_with($line, '.')) {
        sendLine($fp, '.' . $line); // dot-stuffing
    } else {
        sendLine($fp, $line);
    }
}

sendLine($fp, '.');

$resp = readResponse($fp);
foreach ($resp as $l) echo "S: $l\n";
if (!expectCode($resp, 250)) {
    echo "DATA finalization failed\n";
    fclose($fp);
    exit(1);
}

// QUIT
sendLine($fp, 'QUIT');
$resp = readResponse($fp);
foreach ($resp as $l) echo "S: $l\n";

fclose($fp);
echo "Done.\n";
