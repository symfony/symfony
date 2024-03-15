<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Reference;

class AutowireInlineTest extends TestCase
{
    public function testInvalidFactoryArray()
    {
        $autowireInline = new AutowireInline([123, 456]);

        self::assertSame([123, 456], $autowireInline->value['factory']);
    }

    /**
     * @dataProvider provideInvalidCalls
     */
    public function testInvalidCallsArray(array $calls)
    {
        $autowireInline = new AutowireInline('someClass', calls: $calls);

        self::assertSame('someClass', $autowireInline->value['class']);
        self::assertSame($calls, $autowireInline->value['calls']);
    }

    public static function provideInvalidCalls(): iterable
    {
        yield 'missing method' => [[[]]];
        yield 'invalid method value type1' => [[[null]]];
        yield 'invalid method value type2' => [[[123]]];
        yield 'invalid method value type3' => [[[true]]];
        yield 'invalid method value type4' => [[[false]]];
        yield 'invalid method value type5' => [[[new \stdClass()]]];
        yield 'invalid method value type6' => [[[[]]]];

        yield 'invalid arguments value type1' => [[['someMethod', null]]];
        yield 'invalid arguments value type2' => [[['someMethod', 123]]];
        yield 'invalid arguments value type3' => [[['someMethod', true]]];
        yield 'invalid arguments value type4' => [[['someMethod', false]]];
        yield 'invalid arguments value type5' => [[['someMethod', new \stdClass()]]];
        yield 'invalid arguments value type6' => [[['someMethod', '']]];
    }

    public function testClass()
    {
        $attribute = new AutowireInline('someClass');

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertSame('someClass', $buildDefinition->getClass());
        self::assertSame([], $buildDefinition->getArguments());
        self::assertFalse($attribute->lazy);
    }

    public function testClassAndParams()
    {
        $attribute = new AutowireInline('someClass', ['someParam']);

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertSame('someClass', $buildDefinition->getClass());
        self::assertSame(['someParam'], $buildDefinition->getArguments());
        self::assertFalse($attribute->lazy);
    }

    public function testClassAndParamsLazy()
    {
        $attribute = new AutowireInline('someClass', ['someParam'], lazy: true);

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertSame('someClass', $buildDefinition->getClass());
        self::assertSame(['someParam'], $buildDefinition->getArguments());
        self::assertTrue($attribute->lazy);
    }

    /**
     * @dataProvider provideFactories
     */
    public function testFactory(string|array $factory, string|array $expectedResult)
    {
        $attribute = new AutowireInline($factory);

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertNull($buildDefinition->getClass());
        self::assertEquals($expectedResult, $buildDefinition->getFactory());
        self::assertSame([], $buildDefinition->getArguments());
        self::assertFalse($attribute->lazy);
    }

    /**
     * @dataProvider provideFactories
     */
    public function testFactoryAndParams(string|array $factory, string|array $expectedResult)
    {
        $attribute = new AutowireInline($factory, ['someParam']);

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertNull($buildDefinition->getClass());
        self::assertEquals($expectedResult, $buildDefinition->getFactory());
        self::assertSame(['someParam'], $buildDefinition->getArguments());
        self::assertFalse($attribute->lazy);
    }

    /**
     * @dataProvider provideFactories
     */
    public function testFactoryAndParamsLazy(string|array $factory, string|array $expectedResult)
    {
        $attribute = new AutowireInline($factory, ['someParam'], lazy: true);

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertNull($buildDefinition->getClass());
        self::assertEquals($expectedResult, $buildDefinition->getFactory());
        self::assertSame(['someParam'], $buildDefinition->getArguments());
        self::assertTrue($attribute->lazy);
    }

    public static function provideFactories(): iterable
    {
        yield 'string callable' => [[null, 'someFunction'], [null, 'someFunction']];

        yield 'class only' => [['someClass'], ['someClass', '__invoke']];
        yield 'reference only' => [[new Reference('someClass')], [new Reference('someClass'), '__invoke']];

        yield 'class with method' => [['someClass', 'someStaticMethod'], ['someClass', 'someStaticMethod']];
        yield 'reference with method' => [[new Reference('someClass'), 'someMethod'], [new Reference('someClass'), 'someMethod']];
        yield '@reference with method' => [['@someClass', 'someMethod'], [new Reference('someClass'), 'someMethod']];
    }

    /**
     * @dataProvider provideCalls
     */
    public function testCalls(string|array $calls, array $expectedResult)
    {
        $attribute = new AutowireInline('someClass', calls: $calls);

        $buildDefinition = $attribute->buildDefinition($attribute->value, null, $this->createReflectionParameter());

        self::assertSame('someClass', $buildDefinition->getClass());
        self::assertSame($expectedResult, $buildDefinition->getMethodCalls());
        self::assertSame([], $buildDefinition->getArguments());
        self::assertFalse($attribute->lazy);
    }

    public static function provideCalls(): iterable
    {
        yield 'method with empty arguments' => [
            [['someMethod', []]],
            [['someMethod', []]],
        ];
        yield 'method with arguments' => [
            [['someMethod', ['someArgument']]],
            [['someMethod', ['someArgument']]],
        ];
        yield 'method without arguments with return clone true' => [
            [['someMethod', [], true]],
            [['someMethod', [], true]],
        ];
        yield 'method without arguments with return clone false' => [
            [['someMethod', [], false]],
            [['someMethod', []]],
        ];
        yield 'method with arguments with return clone true' => [
            [['someMethod', ['someArgument'], true]],
            [['someMethod', ['someArgument'], true]],
        ];
        yield 'method with arguments with return clone false' => [
            [['someMethod', ['someArgument'], false]],
            [['someMethod', ['someArgument']]],
        ];
    }

    private function createReflectionParameter()
    {
        $class = new class('someValue') {
            public function __construct($someParameter)
            {
            }
        };
        $reflectionClass = new \ReflectionClass($class);

        return $reflectionClass->getConstructor()->getParameters()[0];
    }
}
