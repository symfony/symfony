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
use Symfony\Component\Validator\Constraints\HexColor;
use Symfony\Component\Validator\Constraints\HexColorValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class HexColorValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new HexColorValidator();
    }

    public function testUnexpectedType()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "Symfony\Component\Validator\Constraints\HexColor", "Symfony\Component\Validator\Constraints\Email" given');

        $this->validator->validate(null, new Email());
    }

    public function testUnexpectedValue()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected argument of type "string", "stdClass" given');

        $this->validator->validate(new \stdClass(), new HexColor());
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new HexColor());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new HexColor());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidHexColors
     */
    public function testValidHexColors($hexColor, bool $html5 = true)
    {
        $this->validator->validate($hexColor, new HexColor($html5));

        $this->assertNoViolation();
    }

    public function getValidHexColors()
    {
        // Valid for both patterns
        foreach ([
             '#000000',
             '#abcabc',
             '#BbBbBb',
             new class() {
                 public function __toString(): string
                 {
                     return '#1Ee54d';
                 }
             },
        ] as $hexColor) {
            yield [$hexColor, true];
            yield [$hexColor, false];
        }

        // Only valid with the generic pattern
        foreach ([
            '#abc',
            '#A000',
            '#1e98ccCC',
        ] as $hexColor) {
            yield [$hexColor, false];
        }
    }

    /**
     * @dataProvider getHexColorsWithInvalidFormat
     */
    public function testHexColorsWithInvalidFormat($hexColor, bool $html5 = true)
    {
        $this->validator->validate($hexColor, new HexColor([
            'html5' => $html5,
            'message' => 'foo',
        ]));

        $this->buildViolation('foo')
            ->setParameter('{{ value }}', '"'.(string) $hexColor.'"')
            ->setCode(HexColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getHexColorsWithInvalidFormat()
    {
        // Invalid for both patterns
        foreach ([
            '#',
            '#A',
            '#A1',
            '000000',
            '#abcabg',
            ' #ffffff',
            '#12345',
            new class() {
                public function __toString(): string
                {
                    return '#010101 ';
                }
            },
            '#1e98ccCC9',
        ] as $hexColor) {
            yield [$hexColor, true];
            yield [$hexColor, false];
        }

        // Only invalid with the html5 pattern
        foreach ([
            '#abc',
            '#A000',
            '#1e98ccCC',
        ] as $hexColor) {
            yield [$hexColor, true];
        }
    }
}
