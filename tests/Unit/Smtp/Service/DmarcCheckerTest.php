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

    public function testNoneWhenNoDmarcRecord(): void
    {
        $checker = $this->makeChecker([]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('No DMARC record', $r->message);
    }

    public function testNoneWhenDnsFails(): void
    {
        $checker = $this->makeChecker(false);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        // RFC 7489 — DNS failure → treat as "none"
        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('No DMARC record', $r->message);
    }

    public function testNoneWhenInvalidVersion(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC2; p=reject']]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('No DMARC record', $r->message);
    }

    public function testNoneWhenMissingPolicy(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1']]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('Missing required policy parameter', $r->message);
    }

    public function testPassWhenDkimAlignedEvenIfSpfNotAligned(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=reject; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('other.com', SpfResultStatus::PASS), $this->dkim('example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::PASS, $r->status);
    }

    public function testPassWhenSpfAlignedEvenIfDkimNotAligned(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=reject; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('example.com', SpfResultStatus::PASS), $this->dkim('other.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::PASS, $r->status);
    }

    public function testRejectWhenNoAlignmentAndPolicyReject(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=reject; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('foo.com', SpfResultStatus::PASS), $this->dkim('bar.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::REJECT, $r->status);
        $this->assertStringContainsString('rejected', $r->message);
    }

    public function testNoneWhenNoAlignmentAndPolicyNone(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=none; adkim=s; aspf=s']]);
        $r = $checker->check('example.com', $this->spf('foo.com', SpfResultStatus::PASS), $this->dkim('bar.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::NONE, $r->status);
        $this->assertStringContainsString('policy is none', $r->message);
    }

    public function testPassWithRelaxedAlignment(): void
    {
        $checker = $this->makeChecker([['txt' => 'v=DMARC1; p=quarantine; adkim=r; aspf=r']]);
        $r = $checker->check('example.com', $this->spf('mail.example.com', SpfResultStatus::PASS), $this->dkim('sign.example.com', DkimResultStatus::PASS));

        $this->assertSame(DmarcResultStatus::PASS, $r->status);
    }
}
