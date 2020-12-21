<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Debug\LargeContent;
use Symfony\Component\Serializer\Debug\SerializerActionFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SerializerActionFactoryTest extends TestCase
{
    private const EXPECTED_STRING_RESULT = 'The content of the serialized/deserialized data exceeded 1024 kB.';
    private static $hugeContent;
    private static $obj;
    private $factory;

    public static function setUpBeforeClass(): void
    {
        self::$hugeContent = str_repeat('X', 2000000);

        self::$obj = new \stdClass();
        self::$obj->message = self::$hugeContent;
    }

    public function testCreateLargeContentObjects()
    {
        self::assertSame(
            self::EXPECTED_STRING_RESULT,
            $this->factory->createDeserialization(self::$hugeContent, new \stdClass(), \stdClass::class, 'json')->data
        );

        self::assertInstanceOf(
            LargeContent::class,
            $this->factory->createDeserialization('small-content', self::$obj, \stdClass::class, 'json')->result
        );

        self::assertSame(
            self::EXPECTED_STRING_RESULT,
            $this->factory->createSerialization(new \stdClass(), self::$hugeContent, 'json')->result
        );

        self::assertInstanceOf(
            LargeContent::class,
            $this->factory->createSerialization(self::$obj, 'small-result', 'json')->data
        );
    }

    public function testCreateLargeContentObjectsWithDelegate()
    {
        self::assertSame(
            self::EXPECTED_STRING_RESULT,
            $this->factory->createDenormalization(
                $this->createMock(DenormalizerInterface::class),
                self::$hugeContent,
                new \stdClass(),
                \stdClass::class,
                'json'
            )->data
        );

        self::assertInstanceOf(
            LargeContent::class,
            $this->factory->createDenormalization(
                $this->createMock(DenormalizerInterface::class),
                'small-content',
                self::$obj,
                \stdClass::class,
                'json'
            )->result
        );

        self::assertSame(
            self::EXPECTED_STRING_RESULT,
            $this->factory->createNormalization(
                $this->createMock(NormalizerInterface::class),
                new \stdClass(),
                self::$hugeContent,
                'json'
            )->result
        );

        self::assertInstanceOf(
            LargeContent::class,
            $this->factory->createNormalization(
                $this->createMock(NormalizerInterface::class),
                self::$obj,
                'small-result',
                'json'
            )->data
        );
    }

    protected function setUp(): void
    {
        $this->factory = new SerializerActionFactory();
    }
}
