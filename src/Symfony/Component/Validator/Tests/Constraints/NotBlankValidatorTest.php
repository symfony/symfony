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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotBlankValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NotBlankValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NotBlankValidator
    {
        return new NotBlankValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->validator->validate($value, new NotBlank());

        $this->assertNoViolation();
    }

    public static function getValidValues()
    {
        return [
            ['foobar'],
            [0],
            [0.0],
            ['0'],
            [1234],
        ];
    }

    public function testNullIsInvalid()
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testBlankIsInvalid()
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testFalseIsInvalid()
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate(false, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testEmptyArrayIsInvalid()
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
        ]);

        $this->validator->validate([], $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'array')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public function testAllowNullTrue()
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
            'allowNull' => true,
        ]);

        $this->validator->validate(null, $constraint);
        $this->assertNoViolation();
    }

    public function testAllowNullFalse()
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
            'allowNull' => false,
        ]);

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getWhitespaces
     */
    public function testNormalizedStringIsInvalid($value)
    {
        $constraint = new NotBlank([
            'message' => 'myMessage',
            'normalizer' => 'trim',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setCode(NotBlank::IS_BLANK_ERROR)
            ->assertRaised();
    }

    public static function getWhitespaces()
    {
        return [
            ["\x20"],
            ["\x09\x09"],
            ["\x0A"],
            ["\x0D\x0D"],
            ["\x00"],
            ["\x0B\x0B"],
        ];
    }
}
