<?php

namespace Unit\Smtp\Service;

use App\Smtp\Service\DkimChecker\DkimChecker;
use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultStatus;
use App\Smtp\Service\DmarcChecker\DmarcChecker;
use App\Smtp\Service\DmarcChecker\DmarcResult;
use App\Smtp\Service\DmarcChecker\DmarcResultStatus;
use App\Smtp\Service\DnsResolver;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DmarcCheckerTest extends TestCase
{
    private function makeResolver(array|false $records): DnsResolver
    {
        return new class($records) extends DnsResolver {
            public function __construct(private array|false $records) {}

            public function getRecords(string $host, ?int $type = null): array|false
            {
                return $this->records;
            }
        };
    }

    private function makeChecker(array|false $records): DmarcChecker
    {
        return new DmarcChecker($this->makeResolver($records));
    }

    private function spf(string $domain, SpfResultStatus $status): SpfResult
    {
        return new SpfResult($status, sprintf('SPF result for %s', $domain), $domain);
    }

    private function dkim(string $domain, DkimResultStatus $status): DkimResult
    {
        return new DkimResult($status, sprintf('DKIM result for %s', $domain), $domain);
    }

    public function testNoDmarcRecord(): void
    {
        $checker = $this->makeChecker([]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('No DMARC record', $r->message);
    }

    public function testDnsFails(): void
    {
        $checker = $this->makeChecker(false);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('No DMARC record', $r->message);
    }

    public function testInvalidVersion(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC2; p=reject']]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('Invalid DMARC version', $r->message);
    }

    public function testMissingPolicy(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1']]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('Missing required policy parameter', $r->message);
    }

    public function testDkimAlignedEvenIfSpfNotAligned(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=reject; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('other.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::PASS, $r->status);
    }

    public function testSpfAlignedEvenIfDkimNotAligned(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=reject; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('other.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::PASS, $r->status);
    }

    public function testNoAlignmentAndPolicyReject(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=reject; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('foo.com', SpfResultStatus::PASS), $this->dkim('bar.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::REJECT, $r->status);
        $this->assertStringContainsString('rejected', $r->message);
    }

    public function testNoAlignmentAndPolicyNone(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=none; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('foo.com', SpfResultStatus::PASS), $this->dkim('bar.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('policy is none', $r->message);
    }

    public function testRelaxedAlignment(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=quarantine; adkim=r; aspf=r']]);
        $r = $checker->check('example.com', $this->spf('mail.example.com', SpfResultStatus::PASS), $this->dkim('sign.example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::PASS, $r->status);
    }

    public function testNoAlignmentAndPolicyQuarantine(): void
    {
        $dnsResolver = $this->createMock(DnsResolver::class);
        $dnsResolver->method('getRecords')
            ->willReturn([
                ['txt' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com']
            ]);

        $spf = new SpfResult(SpfResultStatus::FAIL, 'SPF failed');
        $dkim = new DkimResult(DkimResultStatus::FAIL, 'DKIM failed');

        $checker = new DmarcChecker($dnsResolver);
        $result = $checker->check('example.com', $spf, $dkim);

        $this->assertEquals(DmarcResultStatus::QUARANTINE, $result->status);
        $this->assertStringContainsString('quarantine', $result->message);
    }

    public function testSpfAndDkimFail(): void
    {
        $dnsResolver = $this->createMock(DnsResolver::class);
        $dnsResolver->method('getRecords')
            ->willReturn([
                ['txt' => 'v=DMARC1; p=reject']
            ]);

        $spf = new SpfResult(SpfResultStatus::FAIL, 'SPF failed');
        $dkim = new DkimResult(DkimResultStatus::FAIL, 'DKIM failed');

        $checker = new DmarcChecker($dnsResolver);
        $result = $checker->check('example.com', $spf, $dkim);

        $this->assertEquals(DmarcResultStatus::REJECT, $result->status);
        $this->assertStringContainsString('reject', $result->message);
    }

    #[DataProvider('provideDmarcRecords')]
    public function testParseDmarcCheck(string $record, DmarcResultStatus $expected): void
    {
        $dnsResolver = $this->createMock(DnsResolver::class);
        $dnsResolver->method('getRecords')
            ->willReturn([['txt' => $record]]);

        $spf = new SpfResult(SpfResultStatus::FAIL, 'SPF failed');
        $dkim = new DkimResult(DkimResultStatus::FAIL, 'DKIM failed');

        $checker = new DmarcChecker($dnsResolver);
        $result = $checker->check('example.com', $spf, $dkim);

        $this->assertSame($expected, $result->status);
    }

    public static function provideDmarcRecords(): array
    {
        return [
            'normal with spaces' => [
                'v=DMARC1;  p = none ; rua = mailto:test@example.com',
                DmarcResultStatus::NONE,
            ],
            'with invalid part' => [
                'v=DMARC1; p=reject; nonsense; foobar=123',
                DmarcResultStatus::REJECT,
            ],
            'empty value' => [
                'v=DMARC1; p=; rua=mailto:report@example.com',
                DmarcResultStatus::NONE,
            ],
        ];
    }
}
