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
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\BasicTypesController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ArgumentMetadataFactory
     */
    private $factory;

    protected function setUp()
    {
        $this->factory = new ArgumentMetadataFactory();
    }

    public function testSignature1()
    {
        $arguments = $this->factory->createArgumentMetadata(array($this, 'signature1'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', self::class, false, false, null, false),
            new ArgumentMetadata('bar', 'array', false, false, null, false),
            new ArgumentMetadata('baz', 'callable', false, false, null, false),
        ), $arguments);
    }

    public function testSignature2()
    {
        $arguments = $this->factory->createArgumentMetadata(array($this, 'signature2'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', self::class, false, true, null, true),
            new ArgumentMetadata('bar', __NAMESPACE__.'\FakeClassThatDoesNotExist', false, true, null, true),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, true, null, true),
        ), $arguments);
    }

    public function testSignature3()
    {
        $arguments = $this->factory->createArgumentMetadata(array($this, 'signature3'));

        $this->assertEquals(array(
            new ArgumentMetadata('bar', __NAMESPACE__.'\FakeClassThatDoesNotExist', false, false, null, false),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, false, null, false),
        ), $arguments);
    }

    public function testSignature4()
    {
        $arguments = $this->factory->createArgumentMetadata(array($this, 'signature4'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', null, false, true, 'default', false),
            new ArgumentMetadata('bar', null, false, true, 500, false),
            new ArgumentMetadata('baz', null, false, true, array(), false),
        ), $arguments);
    }

    public function testSignature5()
    {
        $arguments = $this->factory->createArgumentMetadata(array($this, 'signature5'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', 'array', false, true, null, true),
            new ArgumentMetadata('bar', null, false, false, null, false),
        ), $arguments);
    }

    /**
     * @requires PHP 5.6
     */
    public function testVariadicSignature()
    {
        $arguments = $this->factory->createArgumentMetadata(array(new VariadicController(), 'action'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', null, false, false, null, false),
            new ArgumentMetadata('bar', null, true, false, null, false),
        ), $arguments);
    }

    /**
     * @requires PHP 7.0
     */
    public function testBasicTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata(array(new BasicTypesController(), 'action'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', 'string', false, false, null, false),
            new ArgumentMetadata('bar', 'int', false, false, null, false),
            new ArgumentMetadata('baz', 'float', false, false, null, false),
        ), $arguments);
    }

    /**
     * @requires PHP 7.1
     */
    public function testNullableTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata(array(new NullableController(), 'action'));

        $this->assertEquals(array(
            new ArgumentMetadata('foo', 'string', false, false, null, true),
            new ArgumentMetadata('bar', \stdClass::class, false, false, null, true),
            new ArgumentMetadata('baz', 'string', false, true, 'value', true),
            new ArgumentMetadata('mandatory', null, false, false, null, false),
        ), $arguments);
    }

    private function signature1(ArgumentMetadataFactoryTest $foo, array $bar, callable $baz)
    {
    }

    private function signature2(ArgumentMetadataFactoryTest $foo = null, FakeClassThatDoesNotExist $bar = null, ImportedAndFake $baz = null)
    {
    }

    private function signature3(FakeClassThatDoesNotExist $bar, ImportedAndFake $baz)
    {
    }

    private function signature4($foo = 'default', $bar = 500, $baz = array())
    {
    }

    private function signature5(array $foo = null, $bar)
    {
    }
}
