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

use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\TypeValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class TypeValidatorTest extends ConstraintValidatorTestCase
{
    protected static $file;

    protected function createValidator(): TypeValidator
    {
        return new TypeValidator();
    }

    public function testNullIsValid()
    {
        $constraint = new Type(type: 'integer');

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyIsValidIfString()
    {
        $constraint = new Type(type: 'string');

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyIsInvalidIfNoString()
    {
        $constraint = new Type(
            type: 'integer',
            message: 'myMessage',
        );

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setParameter('{{ type }}', 'integer')
            ->setCode(Type::INVALID_TYPE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $type)
    {
        $constraint = new Type(type: $type);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidValues()
    {
        $object = new \stdClass();
        $file = self::createFile();

        return [
            [true, 'Boolean'],
            [false, 'Boolean'],
            [true, 'boolean'],
            [false, 'boolean'],
            [true, 'bool'],
            [false, 'bool'],
            [0, 'numeric'],
            ['0', 'numeric'],
            [1.5, 'numeric'],
            ['1.5', 'numeric'],
            [0, 'integer'],
            [1.5, 'float'],
            [\NAN, 'float'],
            [\INF, 'float'],
            [1.5, 'finite-float'],
            [0, 'number'],
            [1.5, 'number'],
            [\INF, 'number'],
            [1.5, 'finite-number'],
            ['12345', 'string'],
            [[], 'array'],
            [[], 'list'],
            [[1, 2, 3], 'list'],
            [['abc' => 1], 'associative_array'],
            [[1 => 1], 'associative_array'],
            [$object, 'object'],
            [$object, 'stdClass'],
            [$file, 'resource'],
            ['12345', 'digit'],
            ['12a34', 'alnum'],
            ['abcde', 'alpha'],
            ["\n\r\t", 'cntrl'],
            ['arf12', 'graph'],
            ['abcde', 'lower'],
            ['ABCDE', 'upper'],
            ['arf12', 'print'],
            ['*&$()', 'punct'],
            ["\n\r\t", 'space'],
            ['AB10BC99', 'xdigit'],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $type, $valueAsString)
    {
        $constraint = new Type(
            type: $type,
            message: 'myMessage',
        );

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $valueAsString)
            ->setParameter('{{ type }}', $type)
            ->setCode(Type::INVALID_TYPE_ERROR)
            ->assertRaised();
    }

    public static function getInvalidValues()
    {
        $object = new \stdClass();
        $file = self::createFile();

        return [
            ['foobar', 'numeric', '"foobar"'],
            ['foobar', 'boolean', '"foobar"'],
            ['0', 'integer', '"0"'],
            [\NAN, 'integer', 'NAN'],
            [\INF, 'integer', 'INF'],
            ['1.5', 'float', '"1.5"'],
            ['1.5', 'finite-float', '"1.5"'],
            [\NAN, 'finite-float', 'NAN'],
            [\INF, 'finite-float', 'INF'],
            ['0', 'number', '"0"'],
            [\NAN, 'number', 'NAN'],
            ['0', 'finite-number', '"0"'],
            [\NAN, 'finite-number', 'NAN'],
            [\INF, 'finite-number', 'INF'],
            [12345, 'string', '12345'],
            [$object, 'boolean', 'object'],
            [$object, 'numeric', 'object'],
            [$object, 'integer', 'object'],
            [$object, 'float', 'object'],
            [$object, 'string', 'object'],
            [$object, 'resource', 'object'],
            [$file, 'boolean', 'resource'],
            [$file, 'numeric', 'resource'],
            [$file, 'integer', 'resource'],
            [$file, 'float', 'resource'],
            [$file, 'string', 'resource'],
            [$file, 'object', 'resource'],
            [[1 => 1], 'list', 'array'],
            [['abc' => 1], 'list', 'array'],
            ['abcd1', 'list', '"abcd1"'],
            [[], 'associative_array', 'array'],
            [[1, 2, 3], 'associative_array', 'array'],
            ['abcd1', 'associative_array', '"abcd1"'],
            ['12a34', 'digit', '"12a34"'],
            ['1a#23', 'alnum', '"1a#23"'],
            ['abcd1', 'alpha', '"abcd1"'],
            ["\nabc", 'cntrl', "\"\nabc\""],
            ["abc\n", 'graph', "\"abc\n\""],
            ['abCDE', 'lower', '"abCDE"'],
            ['ABcde', 'upper', '"ABcde"'],
            ["\nabc", 'print', "\"\nabc\""],
            ['abc&$!', 'punct', '"abc&$!"'],
            ["\nabc", 'space', "\"\nabc\""],
            ['AR1012', 'xdigit', '"AR1012"'],
        ];
    }

    /**
     * @dataProvider getValidValuesMultipleTypes
     */
    public function testValidValuesMultipleTypes($value, array $types)
    {
        $constraint = new Type(type: $types);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidValuesMultipleTypes()
    {
        return [
            ['12345', ['array', 'string']],
            [[], ['array', 'string']],
        ];
    }

    public function testInvalidValuesMultipleTypes()
    {
        $this->validator->validate('12345', new Type(type: ['boolean', 'array'], message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"12345"')
            ->setParameter('{{ type }}', implode('|', ['boolean', 'array']))
            ->setCode(Type::INVALID_TYPE_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testInvalidValuesMultipleTypesDoctrineStyle()
    {
        $this->validator->validate('12345', new Type([
            'type' => ['boolean', 'array'],
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"12345"')
            ->setParameter('{{ type }}', implode('|', ['boolean', 'array']))
            ->setCode(Type::INVALID_TYPE_ERROR)
            ->assertRaised();
    }

    protected static function createFile()
    {
        if (!self::$file) {
            self::$file = fopen(__FILE__, 'r');
        }

        return self::$file;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$file) {
            fclose(self::$file);
            self::$file = null;
        }
    }
}
