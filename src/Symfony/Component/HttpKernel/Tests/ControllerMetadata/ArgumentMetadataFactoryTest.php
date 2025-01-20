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
    private ArgumentMetadataFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ArgumentMetadataFactory();
    }

    public function testSignature1()
    {
        $arguments = $this->factory->createArgumentMetadata([$this, 'signature1']);

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, false, null, controllerName: $this::class.'::signature1'),
            new ArgumentMetadata('bar', 'array', false, false, null, controllerName: $this::class.'::signature1'),
            new ArgumentMetadata('baz', 'callable', false, false, null, controllerName: $this::class.'::signature1'),
        ], $arguments);
    }

    public function testSignature2()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature2(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, true, null, true, controllerName: $this::class.'::signature2'),
            new ArgumentMetadata('bar', FakeClassThatDoesNotExist::class, false, true, null, true, controllerName: $this::class.'::signature2'),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, true, null, true, controllerName: $this::class.'::signature2'),
        ], $arguments);
    }

    public function testSignature3()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature3(...));

        $this->assertEquals([
            new ArgumentMetadata('bar', FakeClassThatDoesNotExist::class, false, false, null, controllerName: $this::class.'::signature3'),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, false, null, controllerName: $this::class.'::signature3'),
        ], $arguments);
    }

    public function testSignature4()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature4(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', null, false, true, 'default', controllerName: $this::class.'::signature4'),
            new ArgumentMetadata('bar', null, false, true, 500, controllerName: $this::class.'::signature4'),
            new ArgumentMetadata('baz', null, false, true, [], controllerName: $this::class.'::signature4'),
        ], $arguments);
    }

    public function testSignature5()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature5(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', 'array', false, true, null, true, controllerName: $this::class.'::signature5'),
            new ArgumentMetadata('bar', null, false, true, null, true, controllerName: $this::class.'::signature5'),
        ], $arguments);
    }

    public function testVariadicSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new VariadicController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('foo', null, false, false, null, controllerName: VariadicController::class.'::action'),
            new ArgumentMetadata('bar', null, true, false, null, controllerName: VariadicController::class.'::action'),
        ], $arguments);
    }

    public function testBasicTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new BasicTypesController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('foo', 'string', false, false, null, controllerName: BasicTypesController::class.'::action'),
            new ArgumentMetadata('bar', 'int', false, false, null, controllerName: BasicTypesController::class.'::action'),
            new ArgumentMetadata('baz', 'float', false, false, null, controllerName: BasicTypesController::class.'::action'),
        ], $arguments);
    }

    public function testNamedClosure()
    {
        $arguments = $this->factory->createArgumentMetadata($this->signature1(...));

        $this->assertEquals([
            new ArgumentMetadata('foo', self::class, false, false, null, controllerName: $this::class.'::signature1'),
            new ArgumentMetadata('bar', 'array', false, false, null, controllerName: $this::class.'::signature1'),
            new ArgumentMetadata('baz', 'callable', false, false, null, controllerName: $this::class.'::signature1'),
        ], $arguments);
    }

    public function testNullableTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new NullableController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('foo', 'string', false, false, null, true, controllerName: NullableController::class.'::action'),
            new ArgumentMetadata('bar', \stdClass::class, false, false, null, true, controllerName: NullableController::class.'::action'),
            new ArgumentMetadata('baz', 'string', false, true, 'value', true, controllerName: NullableController::class.'::action'),
            new ArgumentMetadata('last', 'string', false, true, '', false, controllerName: NullableController::class.'::action'),
        ], $arguments);
    }

    public function testAttributeSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new AttributeController(), 'action']);

        $this->assertEquals([
            new ArgumentMetadata('baz', 'string', false, false, null, false, [new Foo('bar')], controllerName: AttributeController::class.'::action'),
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
            new ArgumentMetadata('baz', 'string', false, false, null, false, [new Foo('bar')], controllerName: AttributeController::class.'::issue41478'),
            new ArgumentMetadata('bat', 'string', false, false, null, false, [], controllerName: AttributeController::class.'::issue41478'),
        ], $arguments);
    }

    public function signature1(self $foo, array $bar, callable $baz)
    {
    }

    public function signature2(?self $foo = null, ?FakeClassThatDoesNotExist $bar = null, ?ImportedAndFake $baz = null)
    {
    }

    public function signature3(FakeClassThatDoesNotExist $bar, ImportedAndFake $baz)
    {
    }

    public function signature4($foo = 'default', $bar = 500, $baz = [])
    {
    }

    public function signature5(?array $foo = null, $bar = null)
    {
    }
}
