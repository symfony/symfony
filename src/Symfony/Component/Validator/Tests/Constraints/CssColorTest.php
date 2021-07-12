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
use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class CssColorTest extends TestCase
{
    public function testConstructorStrict()
    {
        $subject = new CssColor(['mode' => CssColor::VALIDATION_MODE_HEX_SHORT]);

        $this->assertEquals(CssColor::VALIDATION_MODE_HEX_SHORT, $subject->mode);
    }

    public function testUnknownModesTriggerException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "mode" parameter value is not valid.');
        new CssColor(['mode' => 'Unknown Mode']);
    }

    public function testNormalizerCanBeSet()
    {
        $hexaColor = new CssColor(['normalizer' => 'trim']);

        $this->assertEquals('trim', $hexaColor->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new CssColor(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new CssColor(['normalizer' => new \stdClass()]);
    }

    /**
     * @requires PHP 8
     */
    public function testAttribute()
    {
        $metadata = new ClassMetadata(CssColorDummy::class);
        (new AnnotationLoader())->loadClassMetadata($metadata);

        [$aConstraint] = $metadata->properties['a']->constraints;
        self::assertNull($aConstraint->mode);
        self::assertNull($aConstraint->normalizer);

        [$bConstraint] = $metadata->properties['b']->constraints;
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(CssColor::VALIDATION_MODE_HEX_LONG, $bConstraint->mode);
        self::assertSame('trim', $bConstraint->normalizer);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

final class CssColorDummy
{
    #[CssColor]
    private $a;

    #[CssColor(message: 'myMessage', mode: CssColor::VALIDATION_MODE_HEX_LONG, normalizer: 'trim')]
    private $b;

    #[CssColor(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
