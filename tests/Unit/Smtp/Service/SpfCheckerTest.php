<?php

namespace App\Tests\Unit\Smtp\Service;

use App\Smtp\Service\SpfChecker;
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
        $this->assertTrue($result);
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
        $this->assertFalse($result);
    }

    public function testCheckWithNoSpfRecord(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No SPF record found for example.com');

        $checker->check('192.168.1.1', 'example.com');
    }

    public function testCheckWithDnsLookupFailure(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SPF TEMPERROR: DNS lookup failed for example.com');

        $checker->check('192.168.1.1', 'example.com');
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
        $this->assertTrue($result);
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
        $this->assertFalse($result);
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
        $this->assertTrue($result);
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
        $this->assertFalse($result);
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
        $this->assertTrue($result);
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
        $this->assertFalse($result);
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
        $this->assertTrue($result);
    }

    public function testCheckWithMaxDepthExceeded(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->any())
        ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'v=spf1 include:spf2.example.com ~all'],
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SPF PERMERROR: too many DNS lookups');

        // Вызываем проверку с глубиной больше лимита
        $checker->check('192.168.1.1', 'example.com');
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
        $this->assertFalse($result);
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
        $this->assertFalse($result, 'Neutral should not count as pass');
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
        $this->assertTrue($result);
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
        $this->assertTrue($result);
    }

    public function testCheckWithInvalidSpfSyntax(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([
                ['txt' => 'spf1 ip4:192.168.1.1 -all'], // нет "v="
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid SPF record');
        $checker->check('192.168.1.1', 'example.com');
    }

    public function testCheckWithAllDenied(): void
    {
        $checker = $this->createPartialMock(SpfChecker::class, ['getDnsRecords']);
        $checker->expects($this->once())
            ->method('getDnsRecords')
            ->willReturn([['txt' => 'v=spf1 -all']]);

        $result = $checker->check('192.168.1.1', 'example.com');
        $this->assertFalse($result);
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

        // НАДО ВЗЯТЬ ТОЛЬКО ПЕРВУЮ, НО ПОКАЗАТЬ ПОТОМ ОШИБКУ
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Multiple SPF records found');
        $checker->check('192.168.1.1', 'example.com');
    }

    public function testCheckWithMultipleIncludeChain(): void
    {
        $checker = new class extends \App\Smtp\Service\SpfChecker {
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

        $this->assertTrue($checker->check('192.168.1.1', 'example.com'));
    }

    public function testCheckWithIncludeAllAllowed(): void
    {
        $checker = new class extends \App\Smtp\Service\SpfChecker {
            protected function getDnsRecords(string $host, int $type): array|false
            {
                return match ($host) {
                    'example.com' => [['txt' => 'v=spf1 include:spf.example.com ~all']],
                    'spf.example.com' => [['txt' => 'v=spf1 +all']],
                    default => [],
                };
            }
        };

        $this->assertTrue($checker->check('1.1.1.1', 'example.com'));
    }

    public function testCheckWithCyclicInclude(): void
    {
        $checker = new class extends \App\Smtp\Service\SpfChecker {
            protected function getDnsRecords(string $host, int $type): array|false
            {
                return match ($host) {
                    'a.example.com' => [['txt' => 'v=spf1 include:b.example.com ~all']],
                    'b.example.com' => [['txt' => 'v=spf1 include:a.example.com ~all']],
                    default => [],
                };
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SPF PERMERROR: too many DNS lookups');

        $checker->check('1.1.1.1', 'a.example.com');
    }
}
