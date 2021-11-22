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

use Symfony\Component\Validator\Constraints\Color;
use Symfony\Component\Validator\Constraints\ColorValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ColorValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new ColorValidator();
    }

    public function testNullIsValid()
    {
        $constraint = new Color(['type' => 'hex']);

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $constraint = new Color(['type' => 'hex']);

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }


    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $type)
    {
        $constraint = new Color(['type' => $type]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function getValidValues()
    {
        return [
            ['#fff', 'hex'],
            ['#fff', 'HEX'],
            ['#fFF', 'hex'],
            ['#ffffff', 'hex'],
            ['#ffffff', 'HEX'],
            ['rgba(2, 5, 7, 1)', 'rgb'],
            ['rgba(2, 5, 7, 1)', 'RGB'],
            ['RGBA(2, 5, 7, 1)', 'rgb'],
            ['RGBA(2, 5, 7, 1)', 'RGB'],
            ['rgb(2, 5, 7)', 'rgb'],
            ['RGB(2, 5, 7)', 'rgb'],
            ['rgb(2, 5, 7)', 'RGB'],
            ['rgba(2, 5, 7)', 'RGB'],
            ['rgb(2, 5, 7, 1)', 'RGB'],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $type)
    {
        $constraint = new Color([
            'type' => $type,
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $value)
            ->setParameter('{{ type }}', $type)
            ->setCode(Color::INVALID_COLOR_ERROR)
            ->assertRaised();
    }

    public function getInvalidValues()
    {
        return [
            ['#f', 'hex'],
            ['#ff', 'hex'],
            ['#Ff', 'hex'],
            ['#FF', 'hex'],
            ['#ffff', 'HEX'],
            ['#fffff', 'hex'],
            ['#ffg', 'HEX'],
            ['#fffggg', 'HEX'],
            ['rg(2, 5, 7, 1)', 'rgb'],
            ['rb(2, 5, 7, 1)', 'RGB'],
            ['rgbaa(2, 5, 7, 1)', 'rgb'],
            ['RGBAA(2, 5, 7, 1)', 'RGB'],
            ['r(2, 5, 7)', 'rgb'],
            ['255.1.255', 'rgb'],
            ['(255.1.255)', 'rgb'],
            ['string', 'rgb'],
            ['255', 'rgb'],
            ['#fff', 'rgb'],
            ['#ffffff', 'rgb'],
            ['rgba(255, 255, 255, 1)', 'hex'],
            ['rgb(255, 255, 255)', 'hex'],
        ];
    }

    /**
     * @dataProvider getValidValuesMultipleTypes
     */
    public function testValidValuesMultipleTypes($value, array $types)
    {
        $constraint = new Color(['type' => $types]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function getValidValuesMultipleTypes()
    {
        return [
            ['rgba(255, 255, 255, 1)', ['hex', 'rgb']],
            ['rgb(255, 255, 255)', ['hex', 'rgb']],
            ['#fff', ['hex', 'rgb']],
        ];
    }
}
