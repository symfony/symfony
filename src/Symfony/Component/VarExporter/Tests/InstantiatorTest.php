<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\Instantiator;

class InstantiatorTest extends TestCase
{
    public function testNotFoundClass()
    {
        $this->expectException('Symfony\Component\VarExporter\Exception\ClassNotFoundException');
        $this->expectExceptionMessage('Class "SomeNotExistingClass" not found.');
        Instantiator::instantiate('SomeNotExistingClass');
    }

    /**
     * @dataProvider provideFailingInstantiation
     */
    public function testFailingInstantiation(string $class)
    {
        $this->expectException('Symfony\Component\VarExporter\Exception\NotInstantiableTypeException');
        $this->expectExceptionMessageRegExp('/Type ".*" is not instantiable\./');
        Instantiator::instantiate($class);
    }

    public function provideFailingInstantiation()
    {
        yield ['ReflectionClass'];
        yield ['SplHeap'];
        yield ['Throwable'];
        yield ['Closure'];
        yield ['SplFileInfo'];
    }

    public function testInstantiate()
    {
        $this->assertEquals((object) ['p' => 123], Instantiator::instantiate('stdClass', ['p' => 123]));
        $this->assertEquals((object) ['p' => 123], Instantiator::instantiate('STDcLASS', ['p' => 123]));
        $this->assertEquals(new \ArrayObject([123]), Instantiator::instantiate(\ArrayObject::class, ["\0" => [[123]]]));

        $expected = [
            "\0".__NAMESPACE__."\Bar\0priv" => 123,
            "\0".__NAMESPACE__."\Foo\0priv" => 234,
        ];

        $this->assertSame($expected, (array) Instantiator::instantiate(Bar::class, ['priv' => 123], [Foo::class => ['priv' => 234]]));

        $e = Instantiator::instantiate('Exception', ['foo' => 123, 'trace' => [234]]);

        $this->assertSame(123, $e->foo);
        $this->assertSame([234], $e->getTrace());
    }
}

class Foo
{
    private $priv;
}

class Bar extends Foo
{
    private $priv;
}
