<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\Cache\Tests\Fixtures\StringableTag;

class CacheItemTest extends TestCase
{
    #[DataProvider('provideLogMessages')]
    public function testLogWithoutLogger(string $message, array $context, string $expected)
    {
        $warnings = [];
        set_error_handler(static function (int $type, string $message) use (&$warnings): bool {
            $warnings[] = [$type, $message];

            return true;
        }, \E_USER_WARNING);
        try {
            CacheItem::log(null, $message, $context);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([[\E_USER_WARNING, $expected]], $warnings);
    }

    public static function provideLogMessages(): iterable
    {
        yield 'adapter appended' => ['Failed to save "{key}".', ['key' => 'foo', 'cache-adapter' => 'ExampleAdapter'], 'Failed to save "foo". (from "ExampleAdapter")'];
        yield 'explicit placeholder' => ['{cache-adapter}: failed to save "{key}".', ['key' => 'foo', 'cache-adapter' => 'ExampleAdapter'], 'ExampleAdapter: failed to save "foo".'];
        yield 'no adapter' => ['Failed to save "{key}".', ['key' => 'foo'], 'Failed to save "foo".'];
        yield 'null adapter' => ['Failed.', ['cache-adapter' => null], 'Failed.'];
        yield 'non-scalar adapter' => ['Failed.', ['cache-adapter' => new \stdClass()], 'Failed.'];
        yield 'adapter contains placeholder' => ['Failed to save "{key}".', ['key' => 'foo', 'cache-adapter' => 'Adapter{key}'], 'Failed to save "foo". (from "Adapter{key}")'];
    }

    public function testLogWithLoggerPreservesMessageAndContext()
    {
        $message = 'Failed to save "{key}".';
        $context = ['key' => 'foo', 'cache-adapter' => 'ExampleAdapter', 'exception' => new \RuntimeException('Failed')];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($message, $context);

        CacheItem::log($logger, $message, $context);
    }

    public function testValidKey()
    {
        $this->assertSame('foo', CacheItem::validateKey('foo'));
    }

    #[DataProvider('provideInvalidKey')]
    public function testInvalidKey($key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key');
        CacheItem::validateKey($key);
    }

    public static function provideInvalidKey(): array
    {
        return [
            [''],
            ['{'],
            ['}'],
            ['('],
            [')'],
            ['/'],
            ['\\'],
            ['@'],
            [':'],
            [true],
            [null],
            [1],
            [1.1],
            [[[]]],
            [new \Exception('foo')],
        ];
    }

    public function testTag()
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setValue($item, true);

        $this->assertSame($item, $item->tag('foo'));
        $this->assertSame($item, $item->tag(['bar', 'baz']));
        $this->assertSame($item, $item->tag(new StringableTag('qux')));
        $this->assertSame($item, $item->tag([new StringableTag('quux'), new StringableTag('quuux')]));

        (\Closure::bind(function () use ($item) {
            $this->assertSame(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz', 'qux' => 'qux', 'quux' => 'quux', 'quuux' => 'quuux'], $item->newMetadata[CacheItem::METADATA_TAGS]);
        }, $this, CacheItem::class))();
    }

    #[DataProvider('provideInvalidKey')]
    public function testInvalidTag($tag)
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'isTaggable');
        $r->setValue($item, true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache tag');

        $item->tag($tag);
    }

    public function testNonTaggableItem()
    {
        $item = new CacheItem();
        $r = new \ReflectionProperty($item, 'key');
        $r->setValue($item, 'foo');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cache item "foo" comes from a non tag-aware pool: you cannot tag it.');

        $item->tag([]);
    }
}
