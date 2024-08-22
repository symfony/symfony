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

use Symfony\Component\Validator\Constraints\BackedEnumValue;
use Symfony\Component\Validator\Constraints\BackedEnumValueValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class BackedEnumValueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): BackedEnumValueValidator
    {
        return new BackedEnumValueValidator();
    }

    public function testExpectEnumForTypeAttribute()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new BackedEnumValue(
            type: self::class
        );
    }

    public function testNullIsValid()
    {
        $this->validator->validate(
            null,
            new BackedEnumValue(
                type: MyStringBackedEnum::class
            )
        );

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate(
            '',
            new BackedEnumValue(
                type: MyStringBackedEnum::class
            )
        );

        $this->assertNoViolation();
    }

    public function testStringEnumValid()
    {
        $this->validator->validate(
            'yes',
            new BackedEnumValue(
                type: MyStringBackedEnum::class
            )
        );

        $this->assertNoViolation();
    }

    public function testStringEnumWrongValue()
    {
        $this->validator->validate('wrongvalue', new BackedEnumValue(type: MyStringBackedEnum::class));

        $this->buildViolation('The value you selected is not a valid choice.')
            ->setParameter('{{ value }}', '"wrongvalue"')
            ->setParameter('{{ choices }}', '"yes", "no"')
            ->setCode(BackedEnumValue::NO_SUCH_VALUE_ERROR)
            ->assertRaised();
    }

    public function testStringEnumWrongValueWithExcept()
    {
        $this->validator->validate('no', new BackedEnumValue(type: MyStringBackedEnum::class, except: [MyStringBackedEnum::NO]));

        $this->buildViolation('The value you selected is not a valid choice.')
            ->setParameter('{{ value }}', '"no"')
            ->setParameter('{{ choices }}', '"yes"')
            ->setCode(BackedEnumValue::NO_SUCH_VALUE_ERROR)
            ->assertRaised();
    }

    public function testIntEnumValid()
    {
        $this->validator->validate(
            1,
            new BackedEnumValue(
                type: MyIntBackedEnum::class
            )
        );

        $this->assertNoViolation();
    }

    public function testIntEnumWithStringIntSubmitted()
    {
        $this->validator->validate(
            '1',
            new BackedEnumValue(
                type: MyIntBackedEnum::class
            )
        );

        $this->assertNoViolation();
    }

    public function testIntEnumNotValidWithBoolValue()
    {
        $this->validator->validate(
            'bonjour',
            new BackedEnumValue(
                type: MyIntBackedEnum::class
            )
        );

        $this->buildViolation('This value should be of type {{ type }}.')
            ->setParameter('{{ type }}', '"int"')
            ->setCode(BackedEnumValue::INVALID_TYPE_ERROR)
            ->assertRaised();
    }
}

enum MyStringBackedEnum: string
{
    case YES = 'yes';
    case NO = 'no';
}

enum MyIntBackedEnum: int
{
    case YES = 1;
    case NO = 0;
}
