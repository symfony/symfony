<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Har;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\Har\HarFile;

class HarFileTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testStartedDateTimeIsInUtc()
    {
        Clock::set(new MockClock('2026-01-01 12:00:00.123', 'Europe/Paris'));

        $har = HarFile::create()->addEntry('GET', 'https://example.com/', null, [], 200, [], '')->toArray();

        $this->assertSame('2026-01-01T11:00:00.123Z', $har['log']['entries'][0]['startedDateTime']);
    }
}
