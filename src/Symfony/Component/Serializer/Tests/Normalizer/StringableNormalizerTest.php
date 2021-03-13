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
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\StringableDummy;
use Symfony\Component\Serializer\Normalizer\StringableNormalizer;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Tests\Fixtures\JsonSerializableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StringableLegacyDummy;

/**
 * @author Craig Morris <craig.michael.morris@gmail.com>
 */
class StringableNormalizerTest extends TestCase
{
    /**
     * @var StringableNormalizer
     */
    private $normalizer;

    /**
     * @var MockObject|SerializerInterface
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->normalizer = new StringableNormalizer();
    }

    public function testSupportNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new StringableDummy()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->assertSame('hello worlds', $this->normalizer->normalize(new StringableDummy));
    }

    public function testNormalizeLegacy()
    {
        $this->assertSame('hello worlds', $this->normalizer->normalize(new StringableLegacyDummy));
    }
}

abstract class StringNormalizer implements SerializerInterface, NormalizerInterface
{
}
