<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class UuidTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $uuid = new Uuid(['normalizer' => 'trim']);

        $this->assertEquals('trim', $uuid->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Uuid(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Uuid(['normalizer' => new \stdClass()]);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(UuidDummy::class);
        self::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(Uuid::ALL_VERSIONS, $aConstraint->versions);
        self::assertTrue($aConstraint->strict);
        self::assertNull($aConstraint->normalizer);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame([Uuid::V4_RANDOM, Uuid::V6_SORTABLE], $bConstraint->versions);
        self::assertFalse($bConstraint->strict);
        self::assertSame('trim', $bConstraint->normalizer);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'UuidDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class UuidDummy
{
    #[Uuid]
    private $a;

    #[Uuid(message: 'myMessage', versions: [Uuid::V4_RANDOM, Uuid::V6_SORTABLE], normalizer: 'trim', strict: false)]
    private $b;

    #[Uuid(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
