<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\Tests\Fixtures\ProxyDummy;

class ObjectToPopulateTraitTest extends TestCase
{
    use ObjectToPopulateTrait;

    public function testExtractObjectToPopulateReturnsNullWhenKeyIsMissing(): void
    {
        $object = $this->extractObjectToPopulate(ProxyDummy::class, []);

        $this->assertNull($object);
    }

    public function testExtractObjectToPopulateReturnsNullWhenNonObjectIsProvided(): void
    {
        $object = $this->extractObjectToPopulate(ProxyDummy::class, [
            'object_to_populate' => 'not an object',
        ]);

        $this->assertNull($object);
    }

    public function testExtractObjectToPopulateReturnsNullWhenTheClassIsNotAnInstanceOfTheProvidedClass(): void
    {
        $object = $this->extractObjectToPopulate(ProxyDummy::class, [
            'object_to_populate' => new \stdClass(),
        ]);

        $this->assertNull($object);
    }

    public function testExtractObjectToPopulateReturnsObjectWhenEverythingChecksOut(): void
    {
        $expected = new ProxyDummy();
        $object = $this->extractObjectToPopulate(ProxyDummy::class, [
            'object_to_populate' => $expected,
        ]);

        $this->assertSame($expected, $object);
    }
}
