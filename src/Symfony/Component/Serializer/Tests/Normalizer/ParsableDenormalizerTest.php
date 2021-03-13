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
use Symfony\Component\Serializer\Normalizer\ParsableDenormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\ParsableDummy;

class ParsableDenormalizerTest extends TestCase
{
    /**
     * @var ParsableDenormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ParsableDenormalizer();
    }

    public function testSupportDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('', ParsableDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('', \stdClass::class));
    }

    public function testDenormalize()
    {
        $actual = $this->normalizer->denormalize('hello worlds', ParsableDummy::class);

        $this->assertInstanceOf(ParsableDummy::class, $actual);
        $this->assertSame('hello worlds', $actual->str);
    }
}
