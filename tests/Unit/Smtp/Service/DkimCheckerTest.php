<?php

namespace App\Tests\Unit\Smtp\Service;

use App\Smtp\Service\DkimChecker\DkimChecker;
use App\Smtp\Service\DkimChecker\DkimResultStatus;
use PHPUnit\Framework\TestCase;

class DkimCheckerTest extends TestCase
{
    public function testNoDkimHeader(): void
    {
        $checker = new DkimChecker();
        $raw = "From: test@example.com\r\n"
            . "Subject: Hi\r\n\r\n"
            . "Body text";

        $result = $checker->check($raw);

        $this->assertSame(DkimResultStatus::NONE, $result->status);
        $this->assertStringContainsString('not found', $result->message);
    }

    public function testMissingRequiredParameter(): void
    {
        $checker = new DkimChecker();
        $raw = "DKIM-Signature: v=1; a=rsa-sha256; s=sel; bh=abc; b=xyz\r\n\r\nBody";

        $result = $checker->check($raw);

        $this->assertSame(DkimResultStatus::PERMERROR, $result->status);
        $this->assertStringContainsString('Missing required DKIM parameter', $result->message);
    }

    public function testDnsFails(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn(false);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; bh=abc; b=xyz; h=from\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::TEMPERROR, $result->status);
        $this->assertStringContainsString('DNS lookup failed', $result->message);
    }

    public function testNoDnsRecords(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([]);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; bh=abc; b=xyz; h=from\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::PERMERROR, $result->status);
        $this->assertStringContainsString('No DKIM record', $result->message);
    }

    public function testUnsupportedAlgo(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=FAKEKEY']
        ]);

        $raw = "DKIM-Signature: v=1; a=UNKNOWN; d=example.com; s=sel; bh=abc; b=xyz; h=from\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::POLICY, $result->status);
        $this->assertStringContainsString('Unsupported algorithm', $result->message);
    }

    public function testEmptyPublicKey(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=']
        ]);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; bh=abc; b=xyz; h=from\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::PERMERROR, $result->status);
        $this->assertStringContainsString('Public key', $result->message);
    }

    public function testSignatureInvalid(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=FakeKey']
        ]);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; bh=YWJj; b=YWJj; h=from\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertContains($result->status, [
            DkimResultStatus::FAIL,
            DkimResultStatus::TEMPERROR
        ]);
    }

    public function testValidSha256(): void
    {
        $pair = \openssl_pkey_new([
            'private_key_bits' => 512,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        \openssl_pkey_export($pair, $privateKey);
        $details = \openssl_pkey_get_details($pair);
        $publicKeyPem = $details['key'];

        $headers = [
            "From: test@example.com",
            "Subject: DKIM SHA256"
        ];
        $headerList = "from:subject";
        $canonicalizedHeaders = \implode("\r\n", $headers) . "\r\n";

        $ok = openssl_sign($canonicalizedHeaders, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $this->assertTrue($ok, 'OpenSSL failed to sign using SHA256');

        $dkimHeader = sprintf(
            "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=testsel; h=%s; bh=YWJj; b=%s",
            $headerList,
            base64_encode($signature),
        );

        $raw = $dkimHeader . "\r\n" . implode("\r\n", $headers) . "\r\n\r\nBody";

        $pubBody = trim(preg_replace('/-+(BEGIN|END) PUBLIC KEY-+/', '', $publicKeyPem));
        $pubBody = preg_replace('/\s+/', '', $pubBody);

        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=' . $pubBody],
        ]);

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::PASS, $result->status, $result->message);
    }

    public function testValidSha1(): void
    {
        $pair = \openssl_pkey_new([
            'private_key_bits' => 512,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        \openssl_pkey_export($pair, $privateKey);
        $details = \openssl_pkey_get_details($pair);
        $publicKeyPem = $details['key'];

        $headers = [
            "From: test@example.com",
            "Subject: DKIM SHA1",
        ];
        $canonicalizedHeaders = \implode("\r\n", $headers) . "\r\n";

        $ok = \openssl_sign($canonicalizedHeaders, $signature, $privateKey, OPENSSL_ALGO_SHA1);
        $this->assertTrue($ok, 'OpenSSL failed to sign using SHA1');

        $dkimHeader = sprintf(
            "DKIM-Signature: v=1; a=rsa-sha1; d=example.com; s=testsel; h=from:subject; bh=YWJj; b=%s",
            \base64_encode($signature),
        );

        $raw = $dkimHeader . "\r\n" . \implode("\r\n", $headers) . "\r\n\r\nBody";

        $pubBody = \trim(\preg_replace('/-+(BEGIN|END) PUBLIC KEY-+/', '', $publicKeyPem));
        $pubBody = \preg_replace('/\s+/', '', $pubBody);

        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=' . $pubBody],
        ]);

        $result = $mock->check($raw);
        $this->assertSame(DkimResultStatus::PASS, $result->status, $result->message);
    }

    public function testValidEd25519(): void
    {
        $keypair = \sodium_crypto_sign_keypair();
        $priv = \sodium_crypto_sign_secretkey($keypair);
        $pub = \sodium_crypto_sign_publickey($keypair);

        $headers = [
            "From: test@example.com",
            "Subject: DKIM ED25519",
        ];
        $canon = \implode("\r\n", $headers) . "\r\n";
        $sig = \sodium_crypto_sign_detached($canon, $priv);

        $dkim = sprintf(
            "DKIM-Signature: v=1; a=ed25519-sha256; d=example.com; s=sel; h=from:subject; bh=YWJj; b=%s",
            \base64_encode($sig),
        );
        $raw = $dkim . "\r\n" . implode("\r\n", $headers) . "\r\n\r\nBody";

        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=' . \base64_encode($pub)],
        ]);

        $result = $mock->check($raw);
        $this->assertSame(DkimResultStatus::PASS, $result->status, $result->message);
    }

    public function testOpenSslFails(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=' . $this->generatePublicKey()],
        ]);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; h=from; bh=YWJj; b=YWJj\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertContains($result->status, [
            DkimResultStatus::FAIL,
            DkimResultStatus::PERMERROR,
        ]);
    }

    public function testBase64Invalid(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'v=DKIM1; p=' . $this->generatePublicKey()],
        ]);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; h=from; bh=YWJj; b=@@@INVALIDBASE64@@@\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::PERMERROR, $result->status);
        $this->assertStringContainsString('Malformed/invalid signature or unsupported key operation: error:02000077:rsa routines::wrong signature length | error:1C880004:Provider routines::RSA lib', $result->message);
    }

    protected function generatePublicKey(): string
    {
        $res = \openssl_pkey_new([
            'private_key_bits' => 512,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);

        $details = \openssl_pkey_get_details($res);
        $pem = $details['key'] ?? '';

        return \preg_replace(
            [
                '/^-----BEGIN PUBLIC KEY-----/',
                '/-----END PUBLIC KEY-----$/',
                '/\s+/'
            ],
            '',
            $pem
        );
    }

    public function testMissingVersion(): void
    {
        $mock = $this->getMockBuilder(DkimChecker::class)
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $mock->method('getDnsRecords')->willReturn([
            ['txt' => 'p=fake']
        ]);

        $raw = "DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=sel; h=from; bh=YWJj; b=YWJj\r\n";
        $raw.= "From: test@example.com\r\n\r\n";
        $raw.= "Body";

        $result = $mock->check($raw);

        $this->assertSame(DkimResultStatus::PERMERROR, $result->status);
        $this->assertStringContainsString('Invalid or missing DKIM TXT record', $result->message);
    }
}
