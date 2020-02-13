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

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\RgbColor;
use Symfony\Component\Validator\Constraints\RgbColorValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class RgbColorValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new RgbColorValidator();
    }

    public function testInvalidArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "alphaPrecision" option must be a positive integer ("foo" given)');
        
        new RgbColor(['alphaPrecision' => 'foo']);
    }

    public function testUnexpectedType()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "Symfony\Component\Validator\Constraints\RgbColor", "Symfony\Component\Validator\Constraints\Email" given');

        $this->validator->validate(null, new Email());
    }

    public function testUnexpectedValue()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected argument of type "string", "stdClass" given');

        $this->validator->validate(new \stdClass(), new RgbColor());
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new RgbColor());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new RgbColor());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidRgbColorsWithDefaultOptions
     */
    public function testValidRgbColorsWithDefaultOptions($color)
    {
        $this->validator->validate($color, new RgbColor());

        $this->assertNoViolation();
    }

    public function getValidRgbColorsWithDefaultOptions()
    {
        return [
            ['rgb(0,0,0)'],
            ['rgb(255,255,255)'],
            ['RGB(0,0,0)'],
            ['RgB(0,0,0)'],
            ['rgb(0, 0, 0)'],
            ['rgb(0,    0,   0)'],
            ['rgba(0,0,0,0.2)'],
            ['rgba(0,0,0,.75)'],
            ['rgba(0,0,0,1)'],
            [new class() {
                public function __toString()
                {
                    return 'rgb(0,0,0)';
                }
            }],
        ];
    }

    /**
     * @dataProvider getInvalidRgbColorsWithDefaultOptions
     */
    public function testInvalidRgbColorsWithDefaultOptions($color)
    {
        $this->validator->validate($color, new RgbColor(['message' => 'foo']));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', '"'.(string) $color.'"')
            ->setCode(RgbColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidRgbColorsWithDefaultOptions()
    {
        return [
            ['rgb(a,0,0)'],
            ['rgb(0,a,0)'],
            ['rgb(0,0,a)'],
            ['rgb(0,0,0'],
            ['rgb0,0,0)'],
            ['rgb0,0,0'],
            [' rgb(0,0,0)'],
            ['rgb (0,0,0)'],
            ['rgbb(0,0,0)'],
            ['rgba(0,0,0,1.2)'],
            ['rgba(0,0,0,0.751)'],
            ['rgba(0,0,0,.)'],
            ['rgba(0,0,0,3)'],
            ['rgb(-1,0,0)'],
            ['rgb(0,256,0)'],
            ['rgb(0,0,256)'],
            [new class() {
                public function __toString()
                {
                    return 'rgb(0,0,0) ';
                }
            }],
        ];
    }

    /**
     * @dataProvider getValidRgbColorsWithAllowAlphaFalse
     */
    public function testValidRgbColorsWithAllowAlphaFalse($color)
    {
        $this->validator->validate($color, new RgbColor(['allowAlpha' => false]));
        
        $this->assertNoViolation();
    }

    public function getValidRgbColorsWithAllowAlphaFalse()
    {
        return [
            ['rgb(0,0,0)'],
            ['rgb(255,255,255)'],
            ['RGB(0,0,0)'],
            ['RgB(0,0,0)'],
            ['rgb(0, 0, 0)'],
            ['rgb(0,    0,   0)'],
            [new class() {
                public function __toString()
                {
                    return 'rgb(0,0,0)';
                }
            }],
        ];
    }

    /**
     * @dataProvider getInvalidRgbColorsWithAllowAlphaFalse
     */
    public function testInvalidRgbColorsWithAllowAlphaFalse($color)
    {
        $this->validator->validate($color, new RgbColor(['allowAlpha' => false, 'message' => 'foo']));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', '"'.(string) $color.'"')
            ->setCode(RgbColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidRgbColorsWithAllowAlphaFalse()
    {
        return [
            ['rgba(0,0,0,0.2)'],
            ['rgba(0,0,0,.75)'],
        ];
    }

    /**
     * @dataProvider getValidRgbColorsWithLowerCaseOnly
     */
    public function testValidRgbColorsWithLowerCaseOnly($color)
    {
        $this->validator->validate($color, new RgbColor(['lowerCaseOnly' => true]));

        $this->assertNoViolation();
    }

    public function getValidRgbColorsWithLowerCaseOnly()
    {
        return [
            ['rgb(0,0,0)'],
            ['rgb(255,255,255)'],
            ['rgb(0, 0, 0)'],
            ['rgb(0,    0,   0)'],
            ['rgba(0,0,0,0.2)'],
            ['rgba(0,0,0,.75)'],
            ['rgba(0,0,0,1)'],
            [new class() {
                public function __toString()
                {
                    return 'rgb(0,0,0)';
                }
            }],
        ];
    }

    /**
     * @dataProvider getInvalidRgbColorsWithLowerCaseOnly
     */
    public function testInvalidRgbColorsWithLowerCaseOnly($color)
    {
        $this->validator->validate($color, new RgbColor(['lowerCaseOnly' => true, 'message' => 'foo']));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', '"'.(string) $color.'"')
            ->setCode(RgbColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidRgbColorsWithLowerCaseOnly()
    {
        return [
            ['RGB(0,0,0)'],
            ['RgB(0,0,0)'],
            ['rgbA(0,0,0,1)'],
        ];
    }

    /**
     * @dataProvider getValidRgbColorsWithLowerAlphaPrecision
     */
    public function testValidRgbColorsWithLowerAlphaPrecision($color)
    {
        $this->validator->validate($color, new RgbColor(['alphaPrecision' => 1]));

        $this->assertNoViolation();
    }

    public function getValidRgbColorsWithLowerAlphaPrecision()
    {
        return [
            ['rgb(0,0,0)'],
            ['rgb(255,255,255)'],
            ['RGB(0,0,0)'],
            ['RgB(0,0,0)'],
            ['rgb(0, 0, 0)'],
            ['rgb(0,    0,   0)'],
            ['rgba(0,0,0,0.2)'],
            ['rgba(0,0,0,.7)'],
            ['RGBA(0,0,0,1.0)'],
            ['rgba(0,0,0,1)'],
            [new class() {
                public function __toString()
                {
                    return 'rgb(0,0,0)';
                }
            }],
        ];
    }

    /**
     * @dataProvider getInvalidRgbColorsWithLowerAlphaPrecision
     */
    public function testInvalidRgbColorsWithLowerAlphaPrecision($color)
    {
        $this->validator->validate($color, new RgbColor(['alphaPrecision' => 1, 'message' => 'foo']));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', '"'.(string) $color.'"')
            ->setCode(RgbColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidRgbColorsWithLowerAlphaPrecision()
    {
        return [
            ['rgba(0,0,0,.75)'],
            ['rgba(0,0,0,0.75)'],
            ['rgba(0,0,0,1.00)'],
        ];
    }
}