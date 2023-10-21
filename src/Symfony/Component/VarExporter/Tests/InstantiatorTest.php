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
use Symfony\Component\VarExporter\Exception\ClassNotFoundException;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\Instantiator;

class InstantiatorTest extends TestCase
{
    public function testNotFoundClass()
    {
        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class "SomeNotExistingClass" not found.');
        Instantiator::instantiate('SomeNotExistingClass');
    }

    /**
     * @dataProvider provideFailingInstantiation
     */
    public function testFailingInstantiation(string $class)
    {
        $this->expectException(NotInstantiableTypeException::class);
        $this->expectExceptionMessageMatches('/Type ".*" is not instantiable\./');
        Instantiator::instantiate($class);
    }

    public static function provideFailingInstantiation()
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
            "\0*\0prot" => 345,
            "\0".__NAMESPACE__."\Bar\0priv" => 123,
            "\0".__NAMESPACE__."\Foo\0priv" => 234,
            'dyn' => 456,
            'ro' => 567,
        ];

        $actual = (array) Instantiator::instantiate(Bar::class, ['dyn' => 456, 'ro' => 567, 'prot' => 345, 'priv' => 123], [Foo::class => ['priv' => 234]]);
        ksort($actual);

        $this->assertSame($expected, $actual);

        $actual = (array) Instantiator::instantiate(Bar::class, $expected);
        ksort($actual);

        $this->assertSame($expected, $actual);

        $e = Instantiator::instantiate('Exception', ['trace' => [234]]);

        $this->assertSame([234], $e->getTrace());
    }

    public function testPhpReferences()
    {
        $properties = ['p1' => 1];
        $properties['p2'] = &$properties['p1'];

        $obj = Instantiator::instantiate('stdClass', $properties);

        $this->assertSame($properties, (array) $obj);

        $properties['p1'] = 2;
        $this->assertSame(2, $properties['p2']);
        $this->assertSame(2, $obj->p1);
        $this->assertSame(2, $obj->p2);
    }
}

class Foo
{
    protected $prot;
    private $priv;
    public readonly int $ro;
}

#[\AllowDynamicProperties]
class Bar extends Foo
{
    private $priv;
}
