<?php

namespace App\Smtp\Controller;

use App\Smtp\Service\DnsResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('@smtp/index.html.twig');
    }

    #[Route('/spf-checker', name: 'spf-checker')]
    public function spfChecker(
        Request $request,
        DnsResolver $dnsResolver,
    ): Response {
        $domain = $request->query->get('domain');

        $isValid = \strlen($domain) > 0 && \gethostbyname($domain) !== $domain;
        $record = null;
        if ($isValid) {
            foreach ($dnsResolver->getRecords($domain, \DNS_TXT) as $r) {
                if (\stripos($r['txt'] ?? '', 'v=spf1') === 0) {
                    $record = $r['txt'];
                    break;
                }
            }
        }

        return $this->render('@smtp/spf.html.twig', [
            'record' => $record,
            'isValid' => $isValid,
            'result' => $this->parseSpfRecord($record),
        ]);
    }

    private function parseSpfRecord(?string $record): array
    {
        if (\is_null($record)) {
            return [];
        }
        $parts = \explode(' ', $record);
        $result = [];

        foreach ($parts as $part) {
            $prefix = '+';

            if (\in_array($part[0], ['+', '-', '~', '?'], true)) {
                $prefix = $part[0];
                $part = \substr($part, 1);
            }

            if (\str_contains($part, ':')) {
                [$type, $value] = \explode(':', $part, 2);
            } else {
                $type = $part;
                $value = '';
            }

            $result[] = [
                'prefix' => $prefix,
                'type' => $type,
                'value' => $value,
                'prefix_desc' => $this->getPrefixDescription($prefix),
                'description' => $this->getMechanismDescription($type),
            ];
        }

        return $result;
    }

    private function getPrefixDescription(string $p): string
    {
        return match ($p) {
            '+' => 'Pass',
            '-' => 'Fail',
            '~' => 'SoftFail',
            '?' => 'Neutral',
            default => '',
        };
    }

    private function getMechanismDescription(string $type): string
    {
        return match ($type) {
            'v' => 'The SPF record version',
            'include' => 'Includes another SPF record and evaluates it',
            'a' => 'Match A record',
            'mx' => 'Match MX record IPs',
            'ip4' => 'Match IPv4 address',
            'ip6' => 'Match IPv6 address',
            'exists' => 'Match if DNS A exists',
            'all' => 'Always matches',
            default => '',
        };
    }


    #[Route('/dkim-checker', name: 'dkim-checker')]
    public function dkimChecker(
        Request $request,
        DnsResolver $dnsResolver,
    ): Response
    {
        $domain = $request->query->get('domain');
        $selector = $request->query->get('selector');

        $isValid = \strlen($domain ?? '') > 0 && \gethostbyname($domain) !== $domain;
        $selector = \strlen($selector ?? '') > 0 ? $selector : 'default';
        $record = null;
        if ($isValid)
        {
            foreach ($dnsResolver->getRecords($selector . '._domainkey.' . $domain, \DNS_TXT) as $r) {
                if (\stripos($r['txt'] ?? '', 'v=DKIM1') === 0) {
                    $record = $r['txt'];
                    break;
                }
            }
        }

        return $this->render('@smtp/dkim.html.twig', [
            'record' => $record,
            'isValid' => $isValid,
            'data' => $this->buildDkimViewData($this->parseDkimOrDmarcRecord($record)),
        ]);
    }

    #[Route('/dmarc-checker', name: 'dmarc-checker')]
    public function dmarcChecker(
        Request $request,
        DnsResolver $dnsResolver,
    ): Response  {
        $domain = $request->query->get('domain');

        $isValid = \strlen($domain) > 0 && \gethostbyname($domain) !== $domain;
        $record = null;
        if ($isValid) {
            foreach ($dnsResolver->getRecords('_dmarc.' . $domain, \DNS_TXT) as $r) {
                if (\stripos($r['txt'] ?? '', 'v=DMARC1') === 0) {
                    $record = $r['txt'];
                    break;
                }
            }
        }

        return $this->render('@smtp/dmarc.html.twig', [
            'record' => $record,
            'result' => $this->parseSpfRecord($record),
            'isValid' => $isValid,
            'data' => $this->buildDmarcViewData($this->parseDkimOrDmarcRecord($record)),
        ]);
    }

    private function parseDkimOrDmarcRecord(?string $record): array
    {
        if (\is_null($record)) {
            return [];
        }
        $parts = \explode(';', $record);
        $result = [];

        foreach ($parts as $part) {
            $part = \trim($part);
            if ($part === '') {
                continue;
            }

            if (\str_contains($part, '=')) {
                [$key, $value] = \explode('=', $part, 2);
                $result[\strtolower(\trim($key))] = \trim($value);
            }
        }

        return $result;
    }

    private function buildDmarcViewData(array $data): array {
        return [
            'policy' => $data['p'] ?? null,
            'subdomain_policy' => $data['sp'] ?? ($data['p'] ?? null),
            'percent' => $data['pct'] ?? '100',
            'adkim' => ($data['adkim'] ?? 'r') === 's' ? 'strict' : 'relaxed',
            'aspf' => ($data['aspf'] ?? 'r') === 's' ? 'strict' : 'relaxed',
            'rua' => $data['rua'] ?? null,
            'ruf' => $data['ruf'] ?? null,
        ];
    }

    private function buildDkimViewData(array $data): array
    {
        $key = $data['p'] ?? null;
        $bits = $key ? $this->getDkimKeyLength($key) : null;

        return [
            'version' => $data['v'] ?? null,
            'key_type' => $data['k'] ?? null,
            'key_bits' => $bits,
        ];
    }

    private function getDkimKeyLength(string $publicKey): ?int
    {
        $pem =
            "-----BEGIN PUBLIC KEY-----\n" .
            \chunk_split($publicKey, 64) .
            "-----END PUBLIC KEY-----\n";

        $details = \openssl_pkey_get_details(\openssl_pkey_get_public($pem));

        if ($details && isset($details['bits'])) {
            return $details['bits'];
        }

        return null;
    }
}
