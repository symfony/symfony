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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class LengthTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $length = new Length(['min' => 0, 'max' => 10, 'normalizer' => 'trim']);

        $this->assertEquals('trim', $length->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Length(['min' => 0, 'max' => 10, 'normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Length(['min' => 0, 'max' => 10, 'normalizer' => new \stdClass()]);
    }

    public function testDefaultCountUnitIsUsed()
    {
        $length = new Length(['min' => 0, 'max' => 10]);
        $this->assertSame(Length::COUNT_CODEPOINTS, $length->countUnit);
    }

    public function testNonDefaultCountUnitCanBeSet()
    {
        $length = new Length(['min' => 0, 'max' => 10, 'countUnit' => Length::COUNT_GRAPHEMES]);
        $this->assertSame(Length::COUNT_GRAPHEMES, $length->countUnit);
    }

    public function testInvalidCountUnitThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "countUnit" option must be one of the "%s"::COUNT_* constants ("%s" given).', Length::class, 'nonExistentCountUnit'));
        new Length(['min' => 0, 'max' => 10, 'countUnit' => 'nonExistentCountUnit']);
    }

    public function testConstraintDefaultOption()
    {
        $constraint = new Length(5);

        self::assertEquals(5, $constraint->min);
        self::assertEquals(5, $constraint->max);
    }

    public function testConstraintAnnotationDefaultOption()
    {
        $constraint = new Length(['value' => 5, 'exactMessage' => 'message']);

        self::assertEquals(5, $constraint->min);
        self::assertEquals(5, $constraint->max);
        self::assertEquals('message', $constraint->exactMessage);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(LengthDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(42, $aConstraint->min);
        self::assertSame(42, $aConstraint->max);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(1, $bConstraint->min);
        self::assertSame(4711, $bConstraint->max);
        self::assertSame('myMinMessage', $bConstraint->minMessage);
        self::assertSame('myMaxMessage', $bConstraint->maxMessage);
        self::assertSame('trim', $bConstraint->normalizer);
        self::assertSame('ISO-8859-15', $bConstraint->charset);
        self::assertSame(['Default', 'LengthDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class LengthDummy
{
    #[Length(exactly: 42)]
    private $a;

    #[Length(min: 1, max: 4711, minMessage: 'myMinMessage', maxMessage: 'myMaxMessage', normalizer: 'trim', charset: 'ISO-8859-15')]
    private $b;

    #[Length(exactly: 10, groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
