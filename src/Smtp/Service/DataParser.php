<?php

namespace App\Smtp\Service;

class DataParser
{
    /**
     * Парсит EMAIL из SMTP DATA команды
     *
     * @param string $data Сырой текст email из DATA команды
     * @return array ['headers' => array, 'contents' => array]
     */
    public function parse(string $data): array
    {
        // to fix RFC 5321
        $data = \preg_replace('/^\.\.(?=.)/m', '.', $data);

        $parts = \preg_split("/(?:\r\n|\n)(?:\r\n|\n)/", $data, 2);

        if (\count($parts) === 1) {
            if (\preg_match('/(.*?)((?:\r\n|\n)(?![A-Za-z0-9-]+:).+)/s', $data, $matches)) {
                $parts = [$matches[1], \ltrim($matches[2])];
            }
        }

        $headersRaw = $parts[0] ?? '';
        $body = $parts[1] ?? '';

        $headers = $this->parseHeaders($headersRaw);

        $contents = $this->parseContents($headers, $body);

        return [
            'headers' => $headers,
            'contents' => $contents,
        ];
    }

    private function parseHeaders(string $headersRaw): array
    {
        $headers = [];
        $lines = \preg_split("/\r?\n/", $headersRaw);

        $currentHeaderIndex = null;

        foreach ($lines as $line) {
            if (\preg_match('/^[ \t]+/', $line) && $currentHeaderIndex !== null) {
                $headers[$currentHeaderIndex]['value'] .= ' ' . \trim($line);
            } elseif (\preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $matches)) {
                $headerName = \trim($matches[1]);
                $headerValue = \trim($matches[2]);

                $headers[] = [
                    'name' => $headerName,
                    'value' => $headerValue,
                ];

                $currentHeaderIndex = \count($headers) - 1;
            }
        }

        return $headers;
    }

    private function parseContents(array $headers, string $body): array
    {
        $contents = [];
        $contentType = $this->getHeaderValue($headers, 'Content-Type') ?? 'text/plain';

        if (\preg_match('/multipart/i', $contentType)) {
            if (\preg_match('/boundary=["\']{0,1}([^"\';,]+)["\']{0,1}/i', $contentType, $matches)) {
                $boundary = $matches[1];
                $contents = $this->parseMultipart($body, $boundary);
            }
        } else {
            $contentHeaders = $this->extractContentHeaders($headers);

            $contents[] = [
                'type' => $contentType,
                'headers' => $contentHeaders,
                'body' => $body,
                'encoding' => $this->getHeaderValue($headers, 'Content-Transfer-Encoding'),
            ];
        }

        return $contents;
    }

    private function parseMultipart(string $body, string $boundary): array
    {
        $contents = [];

        $parts = \preg_split('/--' . \preg_quote($boundary, '/') . '(--)?\r?\n/', $body);

        foreach ($parts as $part) {
            $part = \trim($part);

            if (empty($part)) {
                continue;
            }

            $partSections = \preg_split("/\r?\n\r?\n/", $part, 2);

            if (\count($partSections) < 2) {
                continue;
            }

            $partHeaders = $this->parseHeaders($partSections[0]);
            $partBody = $partSections[1];

            $contentType = $this->getHeaderValue($partHeaders, 'Content-Type') ?? 'text/plain';

            if (\preg_match('/multipart/i', $contentType)) {
                if (\preg_match('/boundary=["\']{0,1}([^"\';,]+)["\']{0,1}/i', $contentType, $matches)) {
                    $nestedBoundary = $matches[1];
                    $nestedContents = $this->parseMultipart($partBody, $nestedBoundary);
                    $contents = \array_merge($contents, $nestedContents);
                }
            } else {
                $contents[] = [
                    'type' => $contentType,
                    'headers' => $partHeaders,
                    'body' => $partBody,
                    'encoding' => $this->getHeaderValue($partHeaders, 'Content-Transfer-Encoding'),
                    'disposition' => $this->getHeaderValue($partHeaders, 'Content-Disposition'),
                ];
            }
        }

        return $contents;
    }

    private function getHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (\strcasecmp($header['name'], $name) === 0) {
                return $header['value'];
            }
        }
        return null;
    }

    private function extractContentHeaders(array $headers): array
    {
        $contentHeaderNames = [
            'Content-Type',
            'Content-Transfer-Encoding',
            'Content-Disposition',
            'Content-ID',
            'Content-Description',
            'Content-Location',
        ];

        return \array_values(\array_filter($headers, function($header) use ($contentHeaderNames) {
            foreach ($contentHeaderNames as $name) {
                if (\strcasecmp($header['name'], $name) === 0) {
                    return true;
                }
            }
            return false;
        }));
    }

    public function decodeBody(string $body, string $encoding): string
    {
        $encoding = \strtolower(\trim($encoding));

        return match ($encoding) {
            'base64' => \base64_decode($body),
            'quoted-printable' => \quoted_printable_decode($body),
            default => $body,
        };
    }
}
