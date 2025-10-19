<?php

namespace App\Smtp\Service\DkimChecker\ChainHandler;

use App\Smtp\Service\DkimChecker\DkimContext;
use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultFactory;
use App\Smtp\Service\DkimChecker\DkimResultStatus;

final class VerifySignatureHandler extends AbstractDkimHandler
{
    public function handle(DkimContext $context): ?DkimResult
    {
        $factory = new DkimResultFactory($context->domain);

        $algo = \strtolower($context->params['a'] ?? 'rsa-sha256');
        $algoMap = [
            'rsa-sha256' => \OPENSSL_ALGO_SHA256,
            'rsa-sha1' => \OPENSSL_ALGO_SHA1,
            'ed25519-sha256'  => 'ed25519',
        ];

        if (false === \array_key_exists($algo, $algoMap)) {
            return $factory->make(
                DkimResultStatus::POLICY,
                \sprintf('Unsupported algorithm: %s', $algo),
            );
        }

        $headerList = $context->params['h'];
        $canonicalizedHeaders = $this->canonicalizeHeaders($context->headers, $headerList);

        $signature = \base64_decode($context->params['b']);
        if (false === $signature) {
            return $factory->make(
                DkimResultStatus::PERMERROR,
                'Invalid base64 in b=',
            );
        }

        if ('ed25519-sha256' === $algo) {
            $valid = \sodium_crypto_sign_verify_detached(
                $signature,
                $canonicalizedHeaders,
                \base64_decode($context->publicKey)
            );

            if ($valid) {
                return $factory->make(
                    DkimResultStatus::PASS,
                    'Valid Ed25519 DKIM signature',
                );
            }

            return $factory->make(
                DkimResultStatus::FAIL,
                'Invalid Ed25519 DKIM signature',
            );
        }

        $pem = $this->buildRsaPublicKeyPem($context->publicKey);
        $key = \openssl_pkey_get_public($pem);
        if (false === $key) {
            return $factory->make(
                DkimResultStatus::FAIL,
                'Invalid DKIM signature',
            );
        }

        $verify = \openssl_verify($canonicalizedHeaders, $signature, $pem, $algoMap[$algo]);

        $opensslErrors = [];
        while ($err = \openssl_error_string()) {
            $opensslErrors[] = $err;
        }

        if ($verify === 1) {
            return $factory->make(
                DkimResultStatus::PASS,
                'Valid DKIM signature',
            );
        }

        if ($verify === 0) {
            if (\is_array($opensslErrors) && \count($opensslErrors) > 0) {
                return $factory->make(
                    DkimResultStatus::PERMERROR,
                    'Malformed/invalid signature or unsupported key operation',
                );
            }

            return $factory->make(
                DkimResultStatus::FAIL,
                'Invalid DKIM signature',
            );
        }

        return $factory->make(
            DkimResultStatus::TEMPERROR,
            'OpenSSL verification failed',
        );
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

    private function buildRsaPublicKeyPem(string $base64Key): string
    {
        $formatted = \trim(\chunk_split($base64Key, 64, "\n"));

        return "-----BEGIN PUBLIC KEY-----\n"
            . $formatted . "\n"
            . "-----END PUBLIC KEY-----\n";
    }
}
