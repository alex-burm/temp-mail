<?php

namespace App\Smtp\Service\DkimChecker;

class DkimChecker
{
    public function check(string $rawEmail): DkimResult
    {
        try {
            $headers = $this->extractHeaders($rawEmail);
            $dkimHeader = $this->findDkimHeader($headers);

            if (\is_null($dkimHeader)) {
                return $this->none('DKIM-Signature header not found');
            }

            $params = $this->parseDkimParams($dkimHeader);
            if (0 === \count($params)) {
                return $this->neutral('Unable to parse DKIM-Signature header');
            }

            foreach (['v', 'a', 'd', 's', 'bh', 'b'] as $k) {
                if (false === \array_key_exists($k, $params)) {
                    return $this->permError(\sprintf('Missing required DKIM parameter: %s', $k));
                }
            }

            if (\strcasecmp($params['v'], '1') !== 0 && \strcasecmp($params['v'], 'DKIM1') !== 0) {
                return $this->permError('Invalid DKIM version');
            }

            $dnsRecords = $this->resolveRecords($params['s'], $params['d']);
            if ($dnsRecords instanceof DkimResult) {
                return $dnsRecords;
            }

            $record = $this->findDkimDnsRecord($dnsRecords);
            if (\is_null($record)) {
                return $this->permError('Invalid or missing DKIM TXT record');
            }

            $recordParams = $this->parseDkimParams($record);
            if (($recordParams['v'] ?? '') !== 'DKIM1') {
                return $this->permError('DNS DKIM record missing v=DKIM1');
            }

            $publicKey = $recordParams['p'] ?? '';
            if ($publicKey === '') {
                return $this->permError('Public key (p=) missing or empty');
            }

            $algo = \strtolower($params['a'] ?? 'rsa-sha256');
            $algoMap = [
                'rsa-sha256' => \OPENSSL_ALGO_SHA256,
                'rsa-sha1' => \OPENSSL_ALGO_SHA1,
                'ed25519-sha256'  => 'ed25519',
            ];

            if (false === \array_key_exists($algo, $algoMap)) {
                return $this->policy(\sprintf('Unsupported algorithm: %s', $algo));
            }

            $headerList = $params['h'];
            $canonicalizedHeaders = $this->canonicalizeHeaders($headers, $headerList);

            $signature = \base64_decode($params['b']);
            if (false === $signature) {
                return $this->permError('Invalid base64 in b=');
            }

            if ('ed25519-sha256' === $algo) {
                $valid = \sodium_crypto_sign_verify_detached(
                    $signature,
                    $canonicalizedHeaders,
                    \base64_decode($publicKey)
                );

                return $valid
                    ? $this->pass('Valid Ed25519 DKIM signature')
                    : $this->fail('Invalid Ed25519 DKIM signature');
            }

            $pem = $this->buildRsaPublicKeyPem($publicKey);
            $key = \openssl_pkey_get_public($pem);
            if (false === $key) {
                return $this->fail('Invalid DKIM signature');
            }

            $verify = \openssl_verify($canonicalizedHeaders, $signature, $pem, $algoMap[$algo]);

            $opensslErrors = [];
            while ($err = \openssl_error_string()) {
                $opensslErrors[] = $err;
            }

            if ($verify === 1) {
                return $this->pass('Valid DKIM signature');
            }

            if ($verify === 0) {
                if (!empty($opensslErrors)) {
                    return $this->permError('Malformed/invalid signature or unsupported key operation: ' . implode(' | ', $opensslErrors));
                }
                return $this->fail('Invalid DKIM signature');
            }

            return $this->tempError('OpenSSL verification failed' . ($opensslErrors ? ': ' . implode(' | ', $opensslErrors) : ''));
        } catch (\Throwable $e) {
            return $this->permError('Unexpected DKIM processing error: ' . $e->getMessage());
        }
    }

    private function resolveRecords(string $selector, string $domain): array|DkimResult
    {
        $dnsName = \sprintf('%s._domainkey.%s', $selector, $domain);
        $records = $this->getDnsRecords($dnsName, DNS_TXT);

        if (false === $records) {
            return $this->tempError(\sprintf('DNS lookup failed for %s', $dnsName));
        }
        if (\count($records) === 0) {
            return $this->permError(\sprintf('No DKIM record found for %s', $dnsName));
        }

        return $records;
    }

    private function buildRsaPublicKeyPem(string $base64Key): string
    {
        $formatted = \trim(\chunk_split($base64Key, 64, "\n"));

        return "-----BEGIN PUBLIC KEY-----\n"
            . $formatted . "\n"
            . "-----END PUBLIC KEY-----\n";
    }

    protected function getDnsRecords(string $host, int $type): array|false
    {
        return \dns_get_record($host, $type);
    }

    private function extractHeaders(string $rawEmail): array
    {
        $parts = \preg_split("/\r?\n\r?\n/", $rawEmail, 2);
        $headerPart = $parts[0] ?? '';

        $unfolded = \preg_replace("/\r?\n[ \t]+/", ' ', $headerPart);
        return \explode("\r\n", $unfolded);
    }

    private function findDkimHeader(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (\stripos($header, 'DKIM-Signature:') === 0) {
                $value = \substr($header, strlen('DKIM-Signature:'));
                return \trim(\preg_replace('/\r?\n[ \t]+/', ' ', $value));
            }
        }

        return null;
    }

    private function parseDkimParams(string $input): array
    {
        $input = \preg_replace("/\r?\n[ \t]*/", '', $input);

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

    private function findDkimDnsRecord(array $records): ?string
    {
        foreach ($records as $record) {
            $txt = $record['txt'] ?? null;
            if ($txt && \stripos($txt, 'v=DKIM1') === 0) {
                return $txt;
            }
        }
        return null;
    }

    private function canonicalizeHeaders(array $headers, string $headerList): string
    {
        $fields = \array_filter(\array_map(\trim(...), \explode(':', $headerList)));
        $canonical = '';

        foreach ($fields as $name) {
            foreach ($headers as $header) {
                if (\stripos($header, 'dkim-signature:') === 0) {
                    continue;
                }

                if (\stripos($header, $name . ':') === 0) {
                    $canonical .= \rtrim($header) . "\r\n";
                    break;
                }
            }
        }
        return $canonical;
    }

    private function none(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::NONE, $message);
    }

    private function permError(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::PERMERROR, $message);
    }

    private function tempError(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::TEMPERROR, $message);
    }

    private function fail(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::FAIL, $message);
    }

    private function pass(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::PASS, $message);
    }

    private function neutral(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::NEUTRAL, $message);
    }

    private function policy(string $message): DkimResult
    {
        return new DkimResult(DkimResultStatus::POLICY, $message);
    }
}
