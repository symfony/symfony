<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Bridge\PhpUnit\DnsMock;

class SymfonyExtensionWithManualRegister extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClockMock::register(self::class);
        ClockMock::withClockMock(strtotime('2024-05-20 15:30:00'));

        DnsMock::register(self::class);
        DnsMock::withMockedHosts([
            'example.com' => [
                ['type' => 'A', 'ip' => '1.2.3.4'],
            ],
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        ClockMock::withClockMock(false);
        DnsMock::withMockedHosts([]);
    }

    public function testDate()
    {
        self::assertSame('2024-05-20 15:30:00', date('Y-m-d H:i:s'));
    }

    public function testGetHostByName()
    {
        self::assertSame('1.2.3.4', gethostbyname('example.com'));
    }

    public function testTime()
    {
        self::assertSame(1716219000, time());
    }

    public function testDnsGetRecord()
    {
        self::assertSame([[
            'host' => 'example.com',
            'class' => 'IN',
            'ttl' => 1,
            'type' => 'A',
            'ip' => '1.2.3.4',
        ]], dns_get_record('example.com'));
    }
}
