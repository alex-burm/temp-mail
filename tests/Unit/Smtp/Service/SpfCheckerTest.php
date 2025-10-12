<?php

namespace App\Tests\Unit\Smtp\Service;

use App\Smtp\Service\SpfChecker\SpfChecker;
use App\Smtp\Service\SpfChecker\SpfResultStatus;
use PHPUnit\Framework\TestCase;

class SpfCheckerTest extends TestCase
{
    public function testCheckWithValidSpfRecord(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ~all'],
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithInvalidIp(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ~all'],
            ]);
        $result = $checker->check('192.168.1.2', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithNoSpfRecord(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::NONE, $result->status);
        $this->assertEquals('No SPF record found for example.com', $result->message);
    }

    public function testCheckWithDnsLookupFailure(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn(false);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::TEMPERROR, $result->status);
        $this->assertEquals('DNS lookup failed for example.com', $result->message);
    }

    public function testCheckWithIncludeDirectiveMatching(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);

        $checker->expects($this->exactly(2))
            ->method('getDnsRecords')
            ->willReturnMap([
                ['example.com', DNS_TXT, [['txt' => 'v=spf1 include:spf.example.com ~all']]],
                ['spf.example.com', DNS_TXT, [['txt' => 'v=spf1 ip4:192.168.1.1 ~all']]]
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 authorized via include:spf.example.com', $result->message);
    }

    public function testCheckWithIncludeDirectiveNotMatching(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->exactly(2))
            ->method('getDnsRecords')
            ->willReturnMap([
                ['example.com', DNS_TXT, [['txt' => 'v=spf1 include:spf.example.com ~all']]],
                ['spf.example.com', DNS_TXT, [['txt' => 'v=spf1 ip4:192.168.1.2 ~all']]]
            ]);
        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithMultipleIp4Entries(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ip4:192.168.1.2 ~all'],
            ]);
        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithNoMatchingIp(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 ip4:192.168.1.2 ~all'],
            ]);
        $result = $checker->check('192.168.1.3', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithCidrNotation(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.0/24 ~all'],
            ]);
        $result = $checker->check('192.168.1.100', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.100 matches ip4:192.168.1.0/24 in SPF record', $result->message);
    }

    public function testCheckWithCidrNotationNoMatch(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.0/24 ~all'],
            ]);
        $result = $checker->check('192.168.2.100', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithMixedDirectives(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 include:spf2.example.com ~all'],
            ]);
        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithMaxDepthExceeded(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->any())
        ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 include:spf2.example.com ~all'],
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Too many DNS lookups', $result->message);
    }

    public function testCheckWithEmptySpfRecord(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ~all'],
            ]);
        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::SOFTFAIL, $result->status);
        $this->assertEquals('SPF record soft-fails (~all) for example.com', $result->message);
    }

    public function testCheckWithNeutralPolicy(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ?all'],
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::NEUTRAL, $result->status);
        $this->assertEquals('SPF record is neutral (?all) for example.com', $result->message);
    }

    public function testCheckWithAllAllowed(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 +all'],
            ]);

        $result = $checker->check('192.168.100.200', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('SPF record allows all (+all) for example.com', $result->message);
    }

    public function testCheckWithPassPolicy(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 +all'],
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 matches ip4:192.168.1.1 in SPF record', $result->message);
    }

    public function testCheckWithInvalidSpfSyntax(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'spf1 ip4:192.168.1.1 -all'], // нет "v="
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Invalid SPF record for example.com', $result->message);
    }

    public function testCheckWithAllDenied(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([['txt' => 'v=spf1 -all']]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::FAIL, $result->status);
        $this->assertEquals('SPF record forbids this IP (-all) for example.com', $result->message);
    }

    public function testCheckWithMultipleSpfRecords(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 ip4:192.168.1.1 -all'],
                ['txt' => 'v=spf1 ip4:192.168.1.2 -all'],
            ]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Multiple SPF records found for example.com', $result->message);
    }

    public function testCheckWithMultipleIncludeChain(): void
    {
        $checker = new class extends \App\Smtp\Service\SpfChecker\SpfChecker {
            protected function getDnsRecords(string $host, int $type): array|false
            {
                return match ($host) {
                    'example.com' => [['txt' => 'v=spf1 include:spf1.example.com ~all']],
                    'spf1.example.com' => [['txt' => 'v=spf1 include:spf2.example.com ~all']],
                    'spf2.example.com' => [['txt' => 'v=spf1 ip4:192.168.1.1 -all']],
                    default => [],
                };
            }
        };

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 192.168.1.1 authorized via include:spf1.example.com', $result->message);
    }

    public function testCheckWithIncludeAllAllowed(): void
    {
        $checker = new class extends \App\Smtp\Service\SpfChecker\SpfChecker {
            protected function getDnsRecords(string $host, int $type): array|false
            {
                return match ($host) {
                    'example.com' => [['txt' => 'v=spf1 include:spf.example.com ~all']],
                    'spf.example.com' => [['txt' => 'v=spf1 +all']],
                    default => [],
                };
            }
        };

        $result = $checker->check('1.1.1.1', 'example.com');
        $this->assertEquals(SpfResultStatus::PASS, $result->status);
        $this->assertEquals('IP 1.1.1.1 authorized via include:spf.example.com', $result->message);
    }

    public function testCheckWithCyclicInclude(): void
    {
        $checker = new class extends SpfChecker {
            protected function getDnsRecords(string $host, int $type): array|false
            {
                return match ($host) {
                    'a.example.com' => [['txt' => 'v=spf1 include:b.example.com ~all']],
                    'b.example.com' => [['txt' => 'v=spf1 include:a.example.com ~all']],
                    default => [],
                };
            }
        };

        $result = $checker->check('1.1.1.1', 'a.example.com');
        $this->assertEquals(SpfResultStatus::PERMERROR, $result->status);
        $this->assertEquals('Too many DNS lookups', $result->message);
    }
}
