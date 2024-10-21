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

use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\UniqueValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UniqueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new UniqueValidator();
    }

    public function testExpectsUniqueConstraintCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate('', new Unique());
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->validator->validate($value, new Unique());

        $this->assertNoViolation();
    }

    public static function getValidValues()
    {
        return [
            yield 'null' => [[null]],
            yield 'empty array' => [[]],
            yield 'single integer' => [[5]],
            yield 'single string' => [['a']],
            yield 'single object' => [[new \stdClass()]],
            yield 'unique booleans' => [[true, false]],
            yield 'unique integers' => [[1, 2, 3, 4, 5, 6]],
            yield 'unique floats' => [[0.1, 0.2, 0.3]],
            yield 'unique strings' => [['a', 'b', 'c']],
            yield 'unique arrays' => [[[1, 2], [2, 4], [4, 6]]],
            yield 'unique objects' => [[new \stdClass(), new \stdClass()]],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $expectedMessageParam)
    {
        $constraint = new Unique([
            'message' => 'myMessage',
        ]);
        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
             ->setParameter('{{ value }}', $expectedMessageParam)
             ->setCode(Unique::IS_NOT_UNIQUE)
             ->assertRaised();
    }

    public static function getInvalidValues()
    {
        $object = new \stdClass();

        return [
            yield 'not unique booleans' => [[true, true], 'true'],
            yield 'not unique integers' => [[1, 2, 3, 3], 3],
            yield 'not unique floats' => [[0.1, 0.2, 0.1], 0.1],
            yield 'not unique string' => [['a', 'b', 'a'], '"a"'],
            yield 'not unique arrays' => [[[1, 1], [2, 3], [1, 1]], 'array'],
            yield 'not unique objects' => [[$object, $object], 'object'],
        ];
    }

    /**
     * @requires PHP 8
     */
    public function testInvalidValueNamed()
    {
        $constraint = eval('return new \Symfony\Component\Validator\Constraints\Unique(message: "myMessage");');
        $this->validator->validate([1, 2, 3, 3], $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '3')
            ->setCode(Unique::IS_NOT_UNIQUE)
            ->assertRaised();
    }

    /**
     * @dataProvider getCallback
     */
    public function testExpectsUniqueObjects($callback)
    {
        $object1 = new \stdClass();
        $object1->name = 'Foo';
        $object1->email = 'foo@email.com';

        $object2 = new \stdClass();
        $object2->name = 'Foo';
        $object2->email = 'foobar@email.com';

        $object3 = new \stdClass();
        $object3->name = 'Bar';
        $object3->email = 'foo@email.com';

        $value = [$object1, $object2, $object3];

        $this->validator->validate($value, new Unique([
            'normalizer' => $callback,
        ]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getCallback
     */
    public function testExpectsNonUniqueObjects($callback)
    {
        $object1 = new \stdClass();
        $object1->name = 'Foo';
        $object1->email = 'bar@email.com';

        $object2 = new \stdClass();
        $object2->name = 'Foo';
        $object2->email = 'foo@email.com';

        $object3 = new \stdClass();
        $object3->name = 'Foo';
        $object3->email = 'foo@email.com';

        $value = [$object1, $object2, $object3];

        $this->validator->validate($value, new Unique([
            'message' => 'myMessage',
            'normalizer' => $callback,
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'array')
            ->setCode(Unique::IS_NOT_UNIQUE)
            ->assertRaised();
    }

    public static function getCallback()
    {
        return [
            yield 'static function' => [static function (\stdClass $object) {
                return [$object->name, $object->email];
            }],
            yield 'callable with string notation' => ['Symfony\Component\Validator\Tests\Constraints\CallableClass::execute'],
            yield 'callable with static notation' => [[CallableClass::class, 'execute']],
            yield 'callable with object' => [[new CallableClass(), 'execute']],
        ];
    }

    public function testExpectsInvalidNonStrictComparison()
    {
        $this->validator->validate([1, '1', 1.0, '1.0'], new Unique([
            'message' => 'myMessage',
            'normalizer' => 'intval',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '1')
            ->setCode(Unique::IS_NOT_UNIQUE)
            ->assertRaised();
    }

    public function testExpectsValidNonStrictComparison()
    {
        $callback = static function ($item) {
            return (int) $item;
        };

        $this->validator->validate([1, '2', 3, '4.0'], new Unique([
            'normalizer' => $callback,
        ]));

        $this->assertNoViolation();
    }

    public function testExpectsInvalidCaseInsensitiveComparison()
    {
        $callback = static function ($item) {
            return mb_strtolower($item);
        };

        $this->validator->validate(['Hello', 'hello', 'HELLO', 'hellO'], new Unique([
            'message' => 'myMessage',
            'normalizer' => $callback,
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"hello"')
            ->setCode(Unique::IS_NOT_UNIQUE)
            ->assertRaised();
    }

    public function testExpectsValidCaseInsensitiveComparison()
    {
        $callback = static function ($item) {
            return mb_strtolower($item);
        };

        $this->validator->validate(['Hello', 'World'], new Unique([
            'normalizer' => $callback,
        ]));

        $this->assertNoViolation();
    }
}

class CallableClass
{
    public static function execute(\stdClass $object)
    {
        return [$object->name, $object->email];
    }
}
