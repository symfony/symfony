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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Dummy\DummyClassOne;

class UniqueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): UniqueValidator
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

    public function testInvalidValueNamed()
    {
        $constraint = new Unique(message: 'myMessage');
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

    public static function getCallback(): array
    {
        return [
            'static function' => [static fn (\stdClass $object) => [$object->name, $object->email]],
            'callable with string notation' => ['Symfony\Component\Validator\Tests\Constraints\CallableClass::execute'],
            'callable with static notation' => [[CallableClass::class, 'execute']],
            'callable with first-class callable notation' => [CallableClass::execute(...)],
            'callable with object' => [[new CallableClass(), 'execute']],
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
        $callback = static fn ($item) => (int) $item;

        $this->validator->validate([1, '2', 3, '4.0'], new Unique([
            'normalizer' => $callback,
        ]));

        $this->assertNoViolation();
    }

    public function testExpectsInvalidCaseInsensitiveComparison()
    {
        $callback = static fn ($item) => mb_strtolower($item);

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
        $callback = static fn ($item) => mb_strtolower($item);

        $this->validator->validate(['Hello', 'World'], new Unique([
            'normalizer' => $callback,
        ]));

        $this->assertNoViolation();
    }

    public function testCollectionFieldsAreOptional()
    {
        $this->validator->validate([['value' => 5], ['id' => 1, 'value' => 6]], new Unique(fields: 'id'));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidFieldNames
     */
    public function testCollectionFieldNamesMustBeString(string $type, mixed $field)
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage(sprintf('Expected argument of type "string", "%s" given', $type));

        $this->validator->validate([['value' => 5], ['id' => 1, 'value' => 6]], new Unique(fields: [$field]));
    }

    public static function getInvalidFieldNames(): array
    {
        return [
            ['stdClass', new \stdClass()],
            ['int', 2],
            ['bool', false],
        ];
    }

    /**
     * @dataProvider getInvalidCollectionValues
     */
    public function testInvalidCollectionValues(array $value, array $fields, string $expectedMessageParam)
    {
        $this->validator->validate($value, new Unique([
            'message' => 'myMessage',
        ], fields: $fields));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $expectedMessageParam)
            ->setCode(Unique::IS_NOT_UNIQUE)
            ->assertRaised();
    }

    public static function getInvalidCollectionValues(): array
    {
        return [
            'unique string' => [[
                ['lang' => 'eng', 'translation' => 'hi'],
                ['lang' => 'eng', 'translation' => 'hello'],
            ], ['lang'], 'array'],
            'unique floats' => [[
                ['latitude' => 51.509865, 'longitude' => -0.118092, 'poi' => 'capital'],
                ['latitude' => 52.520008, 'longitude' => 13.404954],
                ['latitude' => 51.509865, 'longitude' => -0.118092],
            ], ['latitude', 'longitude'], 'array'],
            'unique int' => [[
                ['id' => 1, 'email' => 'bar@email.com'],
                ['id' => 1, 'email' => 'foo@email.com'],
            ], ['id'], 'array'],
            'unique null' => [
                [null, null],
                [],
                'null',
            ],
            'unique field null' => [
                [['nullField' => null], ['nullField' => null]],
                ['nullField'],
                'array',
            ],
        ];
    }

    public function testArrayOfObjectsUnique()
    {
        $array = [
            new DummyClassOne(),
            new DummyClassOne(),
            new DummyClassOne(),
        ];

        $array[0]->code = '1';
        $array[1]->code = '2';
        $array[2]->code = '3';

        $this->validator->validate(
            $array,
            new Unique(
                normalizer: [self::class, 'normalizeDummyClassOne'],
                fields: 'code'
            )
        );

        $this->assertNoViolation();
    }

    public static function normalizeDummyClassOne(DummyClassOne $obj): array
    {
        return [
            'code' => $obj->code,
        ];
    }
}

class CallableClass
{
    public static function execute(\stdClass $object)
    {
        return [$object->name, $object->email];
    }
}
