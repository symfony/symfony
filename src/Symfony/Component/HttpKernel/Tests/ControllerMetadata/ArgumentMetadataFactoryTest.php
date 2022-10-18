<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\ControllerMetadata;

use Fake\ImportedAndFake;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Foo;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\BasicTypesController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentMetadataFactoryTest extends TestCase
{
    /**
     * @var ArgumentMetadataFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->factory = new ArgumentMetadataFactory();
    }

    public function testSignature1()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature1(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, false, null),
            new ArgumentMetadata('bar', 'array', false, false, null),
            new ArgumentMetadata('baz', 'callable', false, false, null),
        ], $arguments);
    }

    public function testSignature2()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature2(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, true, null, true),
            new ArgumentMetadata('bar', FakeClassThatDoesNotExist::class, false, true, null, true),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, true, null, true),
        ], $arguments);
    }

    public function testSignature3()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature3(...));

        $this->assertEquals([
            new ArgumentMetadata('bar', FakeClassThatDoesNotExist::class, false, false, null),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, false, null),
        ], $arguments);
    }

    public function testSignature4()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature4(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', null, false, true, 'default'),
            new ArgumentMetadata('bar', null, false, true, 500),
            new ArgumentMetadata('baz', null, false, true, []),
        ], $arguments);
    }

    public function testSignature5()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature5(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', 'array', false, true, null, true),
            new ArgumentMetadata('bar', null, false, true, null, true),
        ], $arguments);
    }

    public function testVariadicSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new VariadicController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('foo', null, false, false, null),
            new ArgumentMetadata('bar', null, true, false, null),
        ], $arguments);
    }

    public function testBasicTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new BasicTypesController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('foo', 'string', false, false, null),
            new ArgumentMetadata('bar', 'int', false, false, null),
            new ArgumentMetadata('baz', 'float', false, false, null),
        ], $arguments);
    }

    public function testNamedClosure()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature1(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, false, null),
            new ArgumentMetadata('bar', 'array', false, false, null),
            new ArgumentMetadata('baz', 'callable', false, false, null),
        ], $arguments);
    }

    public function testNullableTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new NullableController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('foo', 'string', false, false, null, true),
            new ArgumentMetadata('bar', \stdClass::class, false, false, null, true),
            new ArgumentMetadata('baz', 'string', false, true, 'value', true),
            new ArgumentMetadata('last', 'string', false, true, '', false),
        ], $arguments);
    }

    public function testAttributeSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new AttributeController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('baz', 'string', false, false, null, false, [new Foo('bar')]),
        ], $arguments);
    }

    public function testMultipleAttributes()
    {
        $this->factory->createArgumentMetadata([new AttributeController(), 'multiAttributeArg']);
        $this->assertCount(1, $this->factory->createArgumentMetadata([new AttributeController(), 'multiAttributeArg'])[0]->getAttributes());
    }

    public function testIssue41478()
    {
        $arguments = $this->factory->createArgumentMetadata([new AttributeController(), 'issue41478']);
        $this->assertEquals([
            new ArgumentMetadata('baz', 'string', false, false, null, false, [new Foo('bar')]),
            new ArgumentMetadata('bat', 'string', false, false, null, false, []),
        ], $arguments);
    }

    public function signature1(self $foo, array $bar, callable $baz)
    {
    }

    public function signature2(self $foo = null, FakeClassThatDoesNotExist $bar = null, ImportedAndFake $baz = null)
    {
    }

    public function signature3(FakeClassThatDoesNotExist $bar, ImportedAndFake $baz)
    {
    }

    public function signature4($foo = 'default', $bar = 500, $baz = [])
    {
    }

    public function signature5(array $foo = null, $bar = null)
    {
    }
}
