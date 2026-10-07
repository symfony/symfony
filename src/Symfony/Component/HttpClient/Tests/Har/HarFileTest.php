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
use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
use Symfony\Component\HttpClient\Har\HarFile;

class HarFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sf_har_file_test';
    }

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        array_map(unlink(...), glob($this->dir.'/records/*'));
        @rmdir($this->dir.'/records');
        @rmdir($this->dir);
    }

    public function testStartedDateTimeIsInUtc()
    {
        Clock::set(new MockClock('2026-01-01 12:00:00.123', 'Europe/Paris'));

        $har = HarFile::create();
        $har->addEntry(HarFile::createEntry('GET', 'https://example.com/', null, [], 200, [], ''));

        $this->assertSame('2026-01-01T11:00:00.123Z', $har->toArray()['log']['entries'][0]['startedDateTime']);
    }

    public function testUpdateAppendsAndReturnsTheIndex()
    {
        $file = $this->dir.'/records/my.har';
        $add = static fn (HarFile $har): int => $har->addEntry(HarFile::createEntry('GET', 'https://example.com/café', null, [], 200, [], 'é'));

        $this->assertSame(0, HarFile::update($file, $add));
        $this->assertSame(1, HarFile::update($file, $add));

        $this->assertSame([$file], glob($this->dir.'/records/*'));
        $this->assertStringContainsString('"url": "https://example.com/café"', file_get_contents($file));
        $this->assertCount(2, HarFile::fromFile($file)->toArray()['log']['entries']);
    }

    public function testFindEntryIndexServesRepeatedEntriesInOrder()
    {
        $har = HarFile::create();
        $har->addEntry(HarFile::createEntry('GET', 'https://example.com/poll', null, [], 202, [], ''));
        $har->addEntry(HarFile::createEntry('GET', 'https://example.com/poll', null, [], 200, [], ''));

        $this->assertSame(0, $har->findEntryIndex('GET', 'https://example.com/poll'));
        $this->assertSame(1, $har->findEntryIndex('GET', 'https://example.com/poll', null, [0]));
        $this->assertSame(1, $har->findEntryIndex('GET', 'https://example.com/poll', null, [0, 1]));

        $this->expectException(HarEntryNotFoundException::class);
        $har->findEntryIndex('GET', 'https://example.com/poll', null, [0, 1], false);
    }

    public function testFindEntryIndexComparesMethodUrlAndBody()
    {
        $har = HarFile::create();
        $har->addEntry(HarFile::createEntry('POST', 'https://example.com/a', 'plain', [], 200, [], ''));
        $har->addEntry(HarFile::createEntry('POST', 'https://example.com/a', "\xFF\xFE", [], 200, [], ''));
        $har->addEntry(HarFile::createEntry('GET', 'https://example.com/b', null, [], 200, [], ''));

        $this->assertSame(0, $har->findEntryIndex('POST', 'https://example.com/a', 'plain'));
        $this->assertSame(1, $har->findEntryIndex('POST', 'https://example.com/a', "\xFF\xFE"));
        $this->assertSame(0, $har->findEntryIndex('POST', 'https://example.com/a'));
        $this->assertSame(2, $har->findEntryIndex('GET', 'https://example.com/b', ''));

        foreach ([['GET', 'https://example.com/a', null], ['POST', 'https://example.com/c', null], ['POST', 'https://example.com/a', 'other'], ['GET', 'https://example.com/b', 'body']] as [$method, $url, $body]) {
            try {
                $har->findEntryIndex($method, $url, $body);
                $this->fail(\sprintf('"%s %s" must not match.', $method, $url));
            } catch (HarEntryNotFoundException) {
            }
        }
    }
}
