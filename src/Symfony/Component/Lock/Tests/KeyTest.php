<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Exception\UnserializableKeyException;
use Symfony\Component\Lock\Key;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class KeyTest extends TestCase
{
    public function testSerialize()
    {
        $key = new Key(__METHOD__);
        $key->reduceLifetime(1);
        $key->setState('foo', 'bar');

        $copy = unserialize(serialize($key));
        $this->assertSame($key->getState('foo'), $copy->getState('foo'));
        $this->assertEqualsWithDelta($key->getRemainingLifetime(), $copy->getRemainingLifetime(), 0.001);
    }

    public function testLegacyPayloadCanBeUnserialized()
    {
        $serialized = base64_decode('TzoyNjoiU3ltZm9ueVxDb21wb25lbnRcTG9ja1xLZXkiOjM6e3M6MzY6IgBTeW1mb255XENvbXBvbmVudFxMb2NrXEtleQByZXNvdXJjZSI7czo2OiJsZWdhY3kiO3M6NDA6IgBTeW1mb255XENvbXBvbmVudFxMb2NrXEtleQBleHBpcmluZ1RpbWUiO047czozMzoiAFN5bWZvbnlcQ29tcG9uZW50XExvY2tcS2V5AHN0YXRlIjthOjA6e319', true);

        $key = unserialize($serialized, ['allowed_classes' => [Key::class]]);

        $this->assertInstanceOf(Key::class, $key);
        $this->assertSame('legacy', (string) $key);
        $this->assertNull($key->getRemainingLifetime());
    }

    public function testUnserialize()
    {
        $key = new Key(__METHOD__);
        $key->markUnserializable();

        $this->expectException(UnserializableKeyException::class);
        serialize($key);
    }
}
