<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\JsonPath\Functions;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonPath\Exception\UnknownJsonPathFunctionException;
use Symfony\Component\JsonPath\Functions\JsonPathFunctionInterface;
use Symfony\Component\JsonPath\Functions\JsonPathFunctionsProvider;

class JsonPathFunctionsProviderTest extends TestCase
{
    public function testConstructorWithEmptyFunctionsStillContainsCoreFunctions()
    {
        $provider = new JsonPathFunctionsProvider();

        $this->assertTrue($provider->hasFunction('length'));
        $this->assertTrue($provider->hasFunction('count'));
        $this->assertTrue($provider->hasFunction('search'));
        $this->assertTrue($provider->hasFunction('value'));
        $this->assertTrue($provider->hasFunction('match'));
    }

    public function testItThrowsOnGettingAnUnknownFunction()
    {
        $provider = new JsonPathFunctionsProvider();

        $this->expectException(UnknownJsonPathFunctionException::class);
        $this->expectExceptionMessage('Unknown JSONPath function "unknownFunc".');

        $provider->getFunction('unknownFunc');
    }

    public function testConstructorWithFunctions()
    {
        $function1 = $this->createMock(JsonPathFunctionInterface::class);

        $function2 = $this->createMock(JsonPathFunctionInterface::class);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(fn ($name) => \in_array($name, ['func1', 'func2'], true));
        $locator->method('get')->willReturnCallback(fn ($name) => match ($name) {
            'func1' => $function1,
            'func2' => $function2,
        });

        $provider = new JsonPathFunctionsProvider($locator);

        $this->assertTrue($provider->hasFunction('func1'));
        $this->assertTrue($provider->hasFunction('func2'));
        $this->assertTrue($provider->hasFunction('length'));
        $this->assertTrue($provider->hasFunction('count'));
        $this->assertTrue($provider->hasFunction('search'));
        $this->assertTrue($provider->hasFunction('value'));
        $this->assertTrue($provider->hasFunction('match'));
    }

    public function testAddFunction()
    {
        $function = $this->createMock(JsonPathFunctionInterface::class);

        $provider = new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['testFunc' => $function]));

        $this->assertTrue($provider->hasFunction('testFunc'));
        $this->assertInstanceOf(JsonPathFunctionInterface::class, $provider->getFunction('testFunc'));
    }

    public function testAddFunctionThrowsExceptionWhenFunctionAlreadyExists()
    {
        $function1 = $this->createMock(JsonPathFunctionInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Function "value" is already registered.');

        new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['value' => $function1]));
    }

    public function testHasFunction()
    {
        $function = $this->createMock(JsonPathFunctionInterface::class);

        $provider = new JsonPathFunctionsProvider();
        $this->assertFalse($provider->hasFunction('nonExistingFunc'));

        $provider = new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['existingFunc' => $function]));
        $this->assertTrue($provider->hasFunction('existingFunc'));
    }

    public function testGetFunctions()
    {
        $function1 = $this->createMock(JsonPathFunctionInterface::class);

        $function2 = $this->createMock(JsonPathFunctionInterface::class);

        $provider = new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['func1' => $function1, 'func2' => $function2]));

        $this->assertInstanceOf(JsonPathFunctionInterface::class, $provider->getFunction('func1'));
        $this->assertInstanceOf(JsonPathFunctionInterface::class, $provider->getFunction('func2'));
    }

    public function testExecuteFunction()
    {
        $function = $this->createMock(JsonPathFunctionInterface::class);
        $function->method('__invoke')->with(['arg1', 'arg2'], 'context')->willReturn('result');

        $provider = new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['testFunc' => $function]));
        $result = $provider->getFunction('testFunc')(['arg1', 'arg2'], 'context');

        $this->assertSame('result', $result);
    }

    public function testExecuteFunctionThrowsExceptionWhenFunctionNotAvailable()
    {
        $provider = new JsonPathFunctionsProvider();

        $this->expectException(UnknownJsonPathFunctionException::class);
        $this->expectExceptionMessage('Unknown JSONPath function "nonExistingFunc".');

        $provider->getFunction('nonExistingFunc')([], 'context');
    }

    public function testExecuteFunctionWithNodelistSizes()
    {
        $function = $this->createMock(JsonPathFunctionInterface::class);
        $function->method('__invoke')->with(['arg1', 'arg2'], 'context')->willReturn('result');

        $provider = new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['testFunc' => $function]));

        $result = $provider->getFunction('testFunc')(['arg1', 'arg2'], 'context');
        $this->assertSame('result', $result);
    }

    public function testExecuteFunctionWithNodelistSizesForNodelistAwareFunction()
    {
        $function = $this->createMock(JsonPathFunctionInterface::class);
        $function->method('__invoke')->with(['arg1', 'arg2'], 'context')->willReturn('result');

        $provider = new JsonPathFunctionsProvider($this->createLocatorWithFunctions(['testFunc' => $function]));

        $result = $provider->getFunction('testFunc')(['arg1', 'arg2'], 'context');
        $this->assertSame('result', $result);
    }

    public function testExecuteFunctionWithNodelistSizesThrowsExceptionWhenFunctionNotAvailable()
    {
        $provider = new JsonPathFunctionsProvider();

        $this->expectException(UnknownJsonPathFunctionException::class);
        $this->expectExceptionMessage('Unknown JSONPath function "nonExistingFunc".');
        $provider->getFunction('nonExistingFunc');
    }

    private function createLocatorWithFunctions(array $functions): ContainerInterface
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(fn ($name) => \in_array($name, array_keys($functions), true));
        $locator->method('get')->willReturnCallback(fn ($name) => $functions[$name]);

        return $locator;
    }
}
