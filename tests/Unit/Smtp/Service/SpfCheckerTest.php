<?php

namespace App\Tests\Unit\Smtp\Service;

use App\Smtp\Service\DnsResolver;
use App\Smtp\Service\SpfChecker\SpfChecker;
use App\Smtp\Service\SpfChecker\SpfResultStatus;
use PHPUnit\Framework\TestCase;

class SpfCheckerTest extends TestCase
{
    public function testCheckWithValidSpfRecord(): void
    {
        $dnsResolver = $this->createMock(DnsResolver::class);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->with('example.com', DNS_TXT)
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ~all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithInvalidIp(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ~all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.2', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithNoSpfRecord(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::NONE, $result->status);
        $this->assertEquals('No SPF record found for example.com', $result->message);
    }

    public function testCheckWithDnsLookupFailure(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn(false);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::TEMPERROR, $result->status);
        $this->assertEquals('DNS lookup failed for example.com', $result->message);
    }

    public function testCheckWithIncludeDirectiveMatching(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->exactly(2))
            ->method('getRecords')
            ->willReturnMap([
                ['example.com', DNS_TXT, [['txt' => 'v=spf1 include:spf.example.com ~all']]],
                ['spf.example.com', DNS_TXT, [['txt' => 'v=spf1 ip4:192.168.1.1 ~all']]]
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 authorized via include:spf.example.com', $result->message);
    }

    public function testCheckWithIncludeDirectiveNotMatching(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->exactly(2))
            ->method('getRecords')
            ->willReturnMap([
                ['example.com', DNS_TXT, [['txt' => 'v=spf1 include:spf.example.com ~all']]],
                ['spf.example.com', DNS_TXT, [['txt' => 'v=spf1 ip4:192.168.1.2 ~all']]]
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithMultipleIp4Entries(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ip4:192.168.1.2 ~all'],
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithNoMatchingIp(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ip4:192.168.1.2 ~all'],
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.1.3', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithCidrNotation(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.0/24 ~all'],
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.1.100', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.100 matches ip4:192.168.1.0/24 in SPF record', $result->message);
    }

    public function testCheckWithCidrNotationNoMatch(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.0/24 ~all'],
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.2.100', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithMixedDirectives(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 include:spf2.example.com ~all'],
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithMaxDepthExceeded(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->any())
        ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 include:spf2.example.com ~all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Too many DNS lookups', $result->message);
    }

    public function testCheckWithEmptySpfRecord(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ~all'],
            ]);
        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithNeutralPolicy(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ?all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::NEUTRAL, $result->status);
        $this->assertEquals('SPF record is neutral (?all) for example.com', $result->message);
    }

    public function testCheckWithAllAllowed(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 +all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.100.200', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('SPF record allows all (+all) for example.com', $result->message);
    }

    public function testCheckWithPassPolicy(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 +all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithInvalidSpfSyntax(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'spf1 ip4:192.168.1.1 -all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Invalid SPF record for example.com', $result->message);
    }

    public function testCheckWithAllDenied(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([['txt' => 'v=spf1 -all']]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::FAIL, $result->status);
        $this->assertEquals('SPF record forbids this IP (-all) for example.com', $result->message);
    }

    public function testCheckWithMultipleSpfRecords(): void
    {
        $dnsResolver = $this->createPartialMock(DnsResolver::class, ['getRecords']);
        $dnsResolver->expects($this->once())
            ->method('getRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 -all'],
                ['txt' => 'v=spf1 ip4:192.168.1.2 -all'],
            ]);

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Multiple SPF records found for example.com', $result->message);
    }

    public function testCheckWithMultipleIncludeChain(): void
    {
        $dnsResolver = new class extends DnsResolver {
            public function getRecords(string $host, ?int $type = null): array|false
            {
                return match ($host) {
                    'example.com' => [['txt' => 'v=spf1 include:spf1.example.com ~all']],
                    'spf1.example.com' => [['txt' => 'v=spf1 include:spf2.example.com ~all']],
                    'spf2.example.com' => [['txt' => 'v=spf1 ip4:192.168.1.1 -all']],
                    default => [],
                };
            }
        };

        $result = (new SpfChecker($dnsResolver))->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 authorized via include:spf1.example.com', $result->message);
    }

    public function testCheckWithIncludeAllAllowed(): void
    {
        $dnsResolver = new class extends DnsResolver {
            public function getRecords(string $host, ?int $type = null): array|false
            {
                return match ($host) {
                    'example.com' => [['txt' => 'v=spf1 include:spf.example.com ~all']],
                    'spf.example.com' => [['txt' => 'v=spf1 +all']],
                    default => [],
                };
            }
        };

        $result = (new SpfChecker($dnsResolver))->check('1.1.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 1.1.1.1 authorized via include:spf.example.com', $result->message);
    }

    public function testCheckWithCyclicInclude(): void
    {
        $dnsResolver = new class extends DnsResolver {
            public function getRecords(string $host, ?int $type = null): array|false
            {
                return match ($host) {
                    'a.example.com' => [['txt' => 'v=spf1 include:b.example.com ~all']],
                    'b.example.com' => [['txt' => 'v=spf1 include:a.example.com ~all']],
                    default => [],
                };
            }
        };

        $result = (new SpfChecker($dnsResolver))->check('1.1.1.1', 'a.example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Too many DNS lookups', $result->message);
    }
}
