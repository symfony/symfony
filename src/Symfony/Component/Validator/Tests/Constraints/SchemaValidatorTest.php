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

use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Schema;
use Symfony\Component\Validator\Constraints\SchemaValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class SchemaValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SchemaValidator
    {
        return new SchemaValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testFormatIsValid(string $format, string $value)
    {
        $this->validator->validate($value, new Schema($format));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testFormatIsInvalid(string $format, string $value, string $errorMsg)
    {
        $this->validator->validate($value, new Schema($format));

        $this->buildViolation('Cannot apply schema validation, this value does not respect format.')
            ->setParameter('{{ error }}', $errorMsg)
            ->setParameter('{{ format }}', $format)
            ->setCode(Schema::INVALID_ERROR)
            ->assertRaised();
    }

    public function testValidWithConstraint()
    {
        $constraint = new Schema(
            format: 'yaml',
            constraints: new NotNull(),
        );

        $this->validator->validate('foo: "bar"', $constraint);
        $this->assertNoViolation();
    }

    public static function getValidValues(): array
    {
        return [
            ['yaml', 'foo: "bar"'],
            ['json', '{"foo":"bar"}'],
        ];
    }

    public static function getInvalidValues(): array
    {
        return [
            ['YAML', 'foo: ["bar"', 'Invalid YAML with message "Malformed inline YAML string at line 1 (near "foo: ["bar"").".'],
            ['JSON', '{"foo:"bar"}', 'Invalid JSON.'],
        ];
    }
}
