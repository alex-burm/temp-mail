<?php

namespace App\Tests\Unit\Smtp\Service;

use App\Smtp\Service\DataParser;
use PHPUnit\Framework\TestCase;

class DataParserTest extends TestCase
{
    private DataParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DataParser();
    }

    public function testMultipartParsing(): void
    {
        $data = <<<EMAIL
From: a@example.com
To: b@example.com
Subject: Test
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="xyz"

--xyz
Content-Type: text/plain

Hello

--xyz
Content-Type: application/pdf
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="doc.pdf"

JVBERi0xLjQK

--xyz--
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertCount(5, $result['headers']);
        $this->assertEquals('a@example.com', $result['headers'][0]['value']);
        $this->assertEquals('multipart/mixed; boundary="xyz"',
            $this->findHeader($result['headers'], 'Content-Type'));

        $this->assertCount(2, $result['contents']);
        $this->assertEquals('text/plain', $result['contents'][0]['type']);
        $this->assertStringContainsString('Hello', $result['contents'][0]['body']);

        $this->assertEquals('application/pdf', $result['contents'][1]['type']);
        $this->assertEquals('base64', $result['contents'][1]['encoding']);
        $this->assertStringContainsString('filename="doc.pdf"',
            $result['contents'][1]['disposition']);
    }

    public function testSimpleEmailParsing(): void
    {
        $data = <<<EMAIL
From: sender@example.com
To: recipient@example.com
Subject: Simple test
Content-Type: text/plain; charset=utf-8

This is a simple email body.
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertCount(4, $result['headers']);
        $this->assertCount(1, $result['contents']);
        $this->assertStringContainsString('simple email body',
            $result['contents'][0]['body']);
    }

    public function testDotStuffingRemoval(): void
    {
        $data = <<<EMAIL
From: test@example.com
To: user@example.com
Subject: Dot test

Line with dot:
..This line started with a dot
Normal line
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertStringContainsString('.This line started',
            $result['contents'][0]['body']);
        $this->assertStringNotContainsString('..This line',
            $result['contents'][0]['body']);
    }

    public function testDotStuffingAtStartOfBody(): void
    {
        $data = <<<EMAIL
From: test@example.com
Subject: Dot at start

..Start with dot
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertStringStartsWith('.Start with dot',
            $result['contents'][0]['body']);
    }

    public function testMultilineHeaders(): void
    {
        $data = <<<EMAIL
From: sender@example.com
Subject: This is a very long subject
 that continues on the next line
 and even one more line
To: recipient@example.com

Body text
EMAIL;

        $result = $this->parser->parse($data);

        $subject = $this->findHeader($result['headers'], 'Subject');
        $this->assertStringContainsString('very long subject', $subject);
        $this->assertStringContainsString('continues on the next line', $subject);
        $this->assertStringContainsString('even one more line', $subject);
        // Проверяем что нет лишних переносов
        $this->assertStringNotContainsString("\n", $subject);
    }

    public function testMultilineHeadersWithTab(): void
    {
        $data = "From: test@example.com\nSubject: Line one\n\tLine two with tab\nTo: user@example.com\n\nBody";

        $result = $this->parser->parse($data);

        $subject = $this->findHeader($result['headers'], 'Subject');
        $this->assertStringContainsString('Line one', $subject);
        $this->assertStringContainsString('Line two with tab', $subject);
    }

    public function testCarriageReturnLineFeed(): void
    {
        $data = "From: test@example.com\r\nTo: user@example.com\r\nSubject: CRLF test\r\n\r\nBody with CRLF";

        $result = $this->parser->parse($data);

        $this->assertCount(3, $result['headers']);
        $this->assertEquals('CRLF test', $this->findHeader($result['headers'], 'Subject'));
        $this->assertStringContainsString('Body with CRLF', $result['contents'][0]['body']);
    }

    public function testEmailWithoutEmptyLineFallback(): void
    {
        // Некорректный формат - нет пустой строки между заголовками и телом
        $data = <<<EMAIL
From: test@example.com
To: user@example.com
Subject: No empty line
Body starts here without empty line
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertCount(3, $result['headers']);
        $this->assertStringContainsString('Body starts here', $result['contents'][0]['body']);
    }

    public function testNestedMultipart(): void
    {
        $data = <<<EMAIL
From: sender@example.com
Subject: Nested multipart
Content-Type: multipart/mixed; boundary="outer"

--outer
Content-Type: multipart/alternative; boundary="inner"

--inner
Content-Type: text/plain

Plain text version

--inner
Content-Type: text/html

<html>HTML version</html>

--inner--

--outer
Content-Type: application/pdf
Content-Disposition: attachment; filename="doc.pdf"

PDF content

--outer--
EMAIL;

        $result = $this->parser->parse($data);

        // Должно быть 3 части: text/plain, text/html, application/pdf
        $this->assertCount(3, $result['contents']);
        $this->assertEquals('text/plain', $result['contents'][0]['type']);
        $this->assertEquals('text/html', $result['contents'][1]['type']);
        $this->assertEquals('application/pdf', $result['contents'][2]['type']);
    }

    public function testBoundaryWithSingleQuotes(): void
    {
        $data = <<<EMAIL
Content-Type: multipart/mixed; boundary='abc123'

--abc123
Content-Type: text/plain

Test

--abc123--
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['contents']);
        $this->assertEquals('text/plain', $result['contents'][0]['type']);
    }

    public function testBoundaryWithoutQuotes(): void
    {
        $data = <<<EMAIL
Content-Type: multipart/mixed; boundary=simplebound

--simplebound
Content-Type: text/plain

Test

--simplebound--
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['contents']);
        $this->assertEquals('text/plain', $result['contents'][0]['type']);
    }

    public function testEmptyParts(): void
    {
        $data = <<<EMAIL
Content-Type: multipart/mixed; boundary="xyz"

--xyz

--xyz
Content-Type: text/plain

Valid part

--xyz

--xyz--
EMAIL;

        $result = $this->parser->parse($data);

        // Пустые части должны игнорироваться
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('text/plain', $result['contents'][0]['type']);
    }

    public function testPartWithoutBody(): void
    {
        $data = <<<EMAIL
Content-Type: multipart/mixed; boundary="xyz"

--xyz
Content-Type: text/plain
--xyz--
EMAIL;

        $result = $this->parser->parse($data);

        // Часть без тела должна игнорироваться (count < 2)
        $this->assertCount(0, $result['contents']);
    }

    public function testDecodeBodyBase64(): void
    {
        $encoded = base64_encode('Hello World');
        $decoded = $this->parser->decodeBody($encoded, 'base64');

        $this->assertEquals('Hello World', $decoded);
    }

    public function testDecodeBodyQuotedPrintable(): void
    {
        $encoded = 'Hello=20World=0A';
        $decoded = $this->parser->decodeBody($encoded, 'quoted-printable');

        $this->assertEquals("Hello World\n", $decoded);
    }

    public function testDecodeBody7Bit(): void
    {
        $text = 'Plain text';
        $decoded = $this->parser->decodeBody($text, '7bit');

        $this->assertEquals('Plain text', $decoded);
    }

    public function testDecodeBody8Bit(): void
    {
        $text = 'Plain text';
        $decoded = $this->parser->decodeBody($text, '8bit');

        $this->assertEquals('Plain text', $decoded);
    }

    public function testDecodeBodyBinary(): void
    {
        $text = 'Binary content';
        $decoded = $this->parser->decodeBody($text, 'binary');

        $this->assertEquals('Binary content', $decoded);
    }

    public function testDecodeBodyUnknownEncoding(): void
    {
        $text = 'Some text';
        $decoded = $this->parser->decodeBody($text, 'unknown-encoding');

        $this->assertEquals('Some text', $decoded);
    }

    public function testDecodeBodyCaseInsensitive(): void
    {
        $encoded = base64_encode('Test');
        $decoded = $this->parser->decodeBody($encoded, 'BASE64');

        $this->assertEquals('Test', $decoded);
    }

    public function testDecodeBodyWithWhitespace(): void
    {
        $encoded = base64_encode('Test');
        $decoded = $this->parser->decodeBody($encoded, '  base64  ');

        $this->assertEquals('Test', $decoded);
    }

    public function testContentHeadersExtraction(): void
    {
        $data = <<<EMAIL
From: sender@example.com
To: recipient@example.com
Content-Type: text/plain
Content-Transfer-Encoding: 7bit
Content-Disposition: inline
X-Custom-Header: value

Body
EMAIL;

        $result = $this->parser->parse($data);

        $contentHeaders = $result['contents'][0]['headers'];

        // Должны быть только Content-* заголовки
        $this->assertGreaterThan(0, count($contentHeaders));

        $headerNames = array_column($contentHeaders, 'name');
        $this->assertContains('Content-Type', $headerNames);
        $this->assertContains('Content-Transfer-Encoding', $headerNames);
        $this->assertContains('Content-Disposition', $headerNames);
        $this->assertNotContains('From', $headerNames);
        $this->assertNotContains('X-Custom-Header', $headerNames);
    }

    public function testEmptyEmail(): void
    {
        $data = '';

        $result = $this->parser->parse($data);

        $this->assertIsArray($result['headers']);
        $this->assertIsArray($result['contents']);
    }

    public function testOnlyHeaders(): void
    {
        $data = <<<EMAIL
From: test@example.com
To: user@example.com

EMAIL;

        $result = $this->parser->parse($data);

        $this->assertCount(2, $result['headers']);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('', $result['contents'][0]['body']);
    }

    public function testHeadersCaseInsensitive(): void
    {
        $data = <<<EMAIL
from: test@example.com
SUBJECT: Test
Content-TYPE: text/plain

Body
EMAIL;

        $result = $this->parser->parse($data);

        $this->assertEquals('test@example.com', $this->findHeader($result['headers'], 'From'));
        $this->assertEquals('Test', $this->findHeader($result['headers'], 'Subject'));
        $this->assertEquals('text/plain', $this->findHeader($result['headers'], 'Content-Type'));
    }

    public function testMultipartWithDifferentLineEndings(): void
    {
        $data = "Content-Type: multipart/mixed; boundary=\"xyz\"\r\n\r\n--xyz\r\nContent-Type: text/plain\r\n\r\nPart1\r\n--xyz\nContent-Type: text/html\n\nPart2\n--xyz--";

        $result = $this->parser->parse($data);

        $this->assertCount(2, $result['contents']);
    }

    public function testDefaultContentTypeForMultipartPart(): void
    {
        $data = <<<EMAIL
Content-Type: multipart/mixed; boundary="xyz"

--xyz

Part without Content-Type header

--xyz--
EMAIL;

        $result = $this->parser->parse($data);

        // Должен использовать text/plain по умолчанию
        $this->assertEquals('text/plain', $result['contents'][0]['type']);
    }

    public function testHeaderWithEmptyValue(): void
    {
        $data = <<<EMAIL
From: test@example.com
Subject:
To: user@example.com

Body
EMAIL;

        $result = $this->parser->parse($data);

        $subject = $this->findHeader($result['headers'], 'Subject');
        $this->assertEquals('', $subject);
    }

    public function testMultipleHeadersWithSameName(): void
    {
        $data = <<<EMAIL
Received: from server1
Received: from server2
Received: from server3
From: test@example.com

Body
EMAIL;

        $result = $this->parser->parse($data);

        $receivedHeaders = array_filter($result['headers'],
            fn($h) => $h['name'] === 'Received');

        $this->assertCount(3, $receivedHeaders);
    }

    private function findHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                return $header['value'];
            }
        }
        return null;
    }
}
