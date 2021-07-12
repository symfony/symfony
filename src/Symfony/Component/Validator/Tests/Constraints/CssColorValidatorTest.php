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

use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Constraints\CssColorValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class CssColorValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CssColorValidator
    {
        return new CssColorValidator(CssColor::VALIDATION_MODE_HEX_LONG);
    }

    public function testUnknownDefaultModeTriggerException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "defaultMode" parameter value is not valid.');
        new CssColorValidator('Unknown Mode');
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new CssColor());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new CssColor());

        $this->assertNoViolation();
    }

    public function testObjectEmptyStringIsValid()
    {
        $this->validator->validate(new EmptyCssColorObject(), new CssColor());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new CssColor());
    }

    /**
     * @dataProvider getValidCssColors
     */
    public function testValidCssColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor());
        $this->assertNoViolation();
    }

    public function getValidCssColors(): array
    {
        return [
            ['#ABCDEF'],
            ['#abcdef'],
            ['#C0FFEE'],
            ['#c0ffee'],
            ['#501311'],
            ['#ABCDEF00'],
            ['#abcdef01'],
            ['#C0FFEE02'],
            ['#c0ffee03'],
            ['#501311FF'],
        ];
    }

    /**
     * @dataProvider getValidShortCssColors
     */
    public function testValidShortCssColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(['mode' => CssColor::VALIDATION_MODE_HEX_SHORT]));
        $this->assertNoViolation();
    }

    public function getValidShortCssColors(): array
    {
        return [
            ['#F4B'],
            ['#FAB'],
            ['#F4B1'],
            ['#FAB1'],
            ['#f4b'],
            ['#fab'],
            ['#f4b1'],
            ['#fab1'],
        ];
    }

    /**
     * @dataProvider getValidNamedColors
     */
    public function testValidNamedColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(['mode' => CssColor::VALIDATION_MODE_NAMED_COLORS]));
        $this->assertNoViolation();
    }

    public function getValidNamedColors(): array
    {
        return [
            ['black'],
            ['red'],
            ['green'],
            ['yellow'],
            ['blue'],
            ['magenta'],
            ['cyan'],
            ['white'],
            ['BLACK'],
            ['RED'],
            ['GREEN'],
            ['YELLOW'],
            ['BLUE'],
            ['MAGENTA'],
            ['CYAN'],
            ['WHITE'],
        ];
    }

    /**
     * @dataProvider getInvalidCssColors
     */
    public function testInvalidCssColors($cssColor)
    {
        $constraint = new CssColor(['message' => 'myMessage']);
        $this->validator->validate($cssColor, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidCssColors(): array
    {
        return [
            ['ABCDEF'],
            ['abcdef'],
            ['#K0FFEE'],
            ['#k0ffee'],
            ['#_501311'],
            ['ABCDEF00'],
            ['abcdefcc'],
            ['#K0FFEE33'],
            ['#k0ffeecc'],
            ['#_50131100'],
            ['#FAℬ'],
            ['#Ⅎab'],
            ['#F4️⃣B'],
            ['#f(4)b'],
            ['#907;'],
        ];
    }

    /**
     * @dataProvider getInvalidShortCssColors
     */
    public function testInvalidShortCssColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            'mode' => CssColor::VALIDATION_MODE_HEX_SHORT,
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidShortCssColors(): array
    {
        return [
            ['ABC'],
            ['ABCD'],
            ['abc'],
            ['abcd'],
            ['#K0F'],
            ['#K0FF'],
            ['#k0f'],
            ['#k0ff'],
            ['#_50'],
            ['#_501'],
        ];
    }

    /**
     * @dataProvider getInvalidNamedColors
     */
    public function testInvalidNamedColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            'mode' => CssColor::VALIDATION_MODE_NAMED_COLORS,
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidNamedColors(): array
    {
        return [
            ['fabpot'],
            ['ngrekas'],
            ['symfony'],
            ['FABPOT'],
            ['NGREKAS'],
            ['SYMFONY'],
        ];
    }

    public function testModeHexLong()
    {
        $constraint = new CssColor(['mode' => CssColor::VALIDATION_MODE_HEX_LONG]);
        $this->validator->validate('#F4B', $constraint);
        $this->buildViolation('This value is not a valid hexadecimal color.')
            ->setParameter('{{ value }}', '"#F4B"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function testModeHexShort()
    {
        $constraint = new CssColor(['mode' => CssColor::VALIDATION_MODE_HEX_SHORT]);
        $this->validator->validate('#F4B', $constraint);
        $this->validator->validate('#F4B1', $constraint);
        $this->assertNoViolation();
    }

    public function testModeNamedColors()
    {
        $constraint = new CssColor(['mode' => CssColor::VALIDATION_MODE_NAMED_COLORS]);
        $this->validator->validate('red', $constraint);
        $this->assertNoViolation();
    }

    public function testUnknownModesOnValidateTriggerException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\CssColor::$mode" parameter value is not valid.');
        $constraint = new CssColor();
        $constraint->mode = 'Unknown Mode';
        $this->validator->validate('#F4B907', $constraint);
    }
}

class EmptyCssColorObject
{
    public function __toString()
    {
        return '';
    }
}
