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
use Symfony\Component\Translation\Provider\NullProvider;
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
            (static function () {
                yield 'foo' => new NullProvider();

                yield 'baz' => new NullProvider();
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
        $provider = new NullProvider();

        $this->assertSame($provider, (new TranslationProviderCollection([
            'foo' => $provider,
            'baz' => new NullProvider(),
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
            'foo' => new NullProvider(),
            'baz' => new NullProvider(),
        ]);
    }
}
