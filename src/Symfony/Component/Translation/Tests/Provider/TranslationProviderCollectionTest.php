<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

class TranslationProviderCollectionTest extends TestCase
{
    public function testKeys()
    {
        $this->assertSame(['foo', 'baz'], $this->createProviderCollection()->keys());
    }

    public function testKeysWithGenerator()
    {
        $this->assertSame(['foo', 'baz'], (new TranslationProviderCollection(
            (function () {
                yield 'foo' => $this->createMock(ProviderInterface::class);

                yield 'baz' => $this->createMock(ProviderInterface::class);
            })()
        ))->keys());
    }

    public function testToString()
    {
        $this->assertSame('[foo,baz]', (string) $this->createProviderCollection());
    }

    public function testHas()
    {
        $this->assertTrue($this->createProviderCollection()->has('foo'));
    }

    public function testGet()
    {
        $provider = $this->createMock(ProviderInterface::class);

        $this->assertSame($provider, (new TranslationProviderCollection([
            'foo' => $provider,
            'baz' => $this->createMock(ProviderInterface::class),
        ]))->get('foo'));
    }

    public function testGetThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider "invalid" not found. Available: "[foo,baz]".');

        $this->createProviderCollection()->get('invalid');
    }

    private function createProviderCollection(): TranslationProviderCollection
    {
        return new TranslationProviderCollection([
            'foo' => $this->createMock(ProviderInterface::class),
            'baz' => $this->createMock(ProviderInterface::class),
        ]);
    }
}
