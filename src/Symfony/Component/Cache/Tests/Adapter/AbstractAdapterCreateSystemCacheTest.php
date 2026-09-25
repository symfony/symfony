<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Filesystem;

class AbstractAdapterCreateSystemCacheTest extends TestCase
{
    private string $directory;
    private string $version;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/symfony-system-cache-'.bin2hex(random_bytes(6));
        $this->version = bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $fs = new Filesystem();
            $fs->chmod($this->directory, 0o777, 0o000, true);
            $fs->remove($this->directory);
        }
    }

    public function testWritableDirectoryWithOpcacheUsesPhpFilesAdapterAlone()
    {
        if (!PhpFilesAdapter::isSupported()) {
            $this->markTestSkipped('OPcache is required.');
        }

        $this->assertInstanceOf(PhpFilesAdapter::class, AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory));
    }

    public function testWithoutApcuUsesPhpFilesAdapterAlone()
    {
        if (self::isApcuUsable()) {
            $this->markTestSkipped('APCu must not be usable.');
        }

        $this->assertInstanceOf(PhpFilesAdapter::class, AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory));
    }

    public function testReadOnlyDirectoryPutsApcuInFrontOfPhpFiles()
    {
        $this->requireApcu();
        $this->makeReadOnly();

        $this->assertInstanceOf(ChainAdapter::class, AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory));
    }

    #[RunInSeparateProcess]
    public function testDisabledOpcachePutsApcuInFrontOfPhpFiles()
    {
        $this->requireApcu();
        ini_set('opcache.enable', '0');

        $this->assertInstanceOf(ChainAdapter::class, AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory));
    }

    public function testReadOnlyDirectoryServesWarmedUpItemsAndKeepsMissesInApcu()
    {
        $this->requireApcu();
        $warmer = new PhpFilesAdapter('ns', 0, $this->directory, true);
        $warmer->save($warmer->getItem('warm')->set(['from' => 'warm-up']));
        $this->makeReadOnly();

        $pool = AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory);
        $this->assertSame(['from' => 'warm-up'], $pool->get('warm', static fn () => 'computed'));
        $this->assertSame('computed', $pool->get('miss', static fn () => 'computed'));

        $pool = AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory);
        $this->assertSame(['from' => 'warm-up'], $pool->get('warm', static fn () => 'computed again'));
        $this->assertSame('computed', $pool->get('miss', static fn () => 'computed again'));
    }

    #[DataProvider('provideDirectoryModes')]
    public function testReturnedAdapterKeepsItemsAcrossInstances(bool $readOnly)
    {
        if ($readOnly) {
            $this->requireApcu();
            $this->makeReadOnly();
        }

        $pool = AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory);
        $object = new \stdClass();
        $object->foo = 'bar';
        $this->assertEquals($object, $pool->get('object', static fn () => $object));
        $this->assertFalse($pool->getItem('array')->isHit());
        $pool->save($pool->getItem('array')->set([1, 2, 3]));

        $pool = AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory);
        $fetched = $pool->get('object', static fn () => null);
        $this->assertEquals($object, $fetched);
        $this->assertNotSame($object, $fetched);
        $fetched->foo = 'baz';
        $this->assertEquals($object, $pool->get('object', static fn () => null));
        $this->assertTrue($pool->hasItem('array'));
        $this->assertSame([1, 2, 3], $pool->getItem('array')->get());
        $this->assertTrue($pool->deleteItem('array'));
        $this->assertFalse($pool->getItem('array')->isHit());

        $pool = AbstractAdapter::createSystemCache('ns', 0, $this->version, $this->directory);
        $this->assertFalse($pool->hasItem('array'));
        $this->assertTrue($pool->hasItem('object'));
    }

    public static function provideDirectoryModes(): iterable
    {
        yield 'writable' => [false];
        yield 'read-only' => [true];
    }

    private function requireApcu(): void
    {
        if (!self::isApcuUsable()) {
            $this->markTestSkipped('APCu is required, with apc.enable_cli=1 on the CLI.');
        }
    }

    private function makeReadOnly(): void
    {
        $fs = new Filesystem();
        $fs->mkdir($this->directory);
        $fs->chmod($this->directory, 0o555, 0o000, true);

        if (is_writable($this->directory)) {
            $this->markTestSkipped('The cache directory cannot be made read-only.');
        }
    }

    private static function isApcuUsable(): bool
    {
        return ApcuAdapter::isSupported() && ('cli' !== \PHP_SAPI || filter_var(\ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOL));
    }
}
