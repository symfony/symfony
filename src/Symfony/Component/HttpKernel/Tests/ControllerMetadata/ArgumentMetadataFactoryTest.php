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
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $factory;

    protected function setUp()
    {
        $this->factory = new ArgumentMetadataFactory();
    }

    public function testSignature1()
    {
        $arguments = $this->factory->createArgumentMetadata([$this, 'signature1']);

        $this->assertEquals(array(
            new ArgumentMetadata('foo', self::class, false, false, null),
            new ArgumentMetadata('bar', 'array', false, false, null),
            new ArgumentMetadata('baz', 'callable', false, false, null),
        ), $arguments);
    }

    public function testSignature2()
    {
        $arguments = $this->factory->createArgumentMetadata([$this, 'signature2']);

        $this->assertEquals(array(
            new ArgumentMetadata('foo', self::class, false, true, null),
            new ArgumentMetadata('bar', __NAMESPACE__.'\FakeClassThatDoesNotExist', false, true, null),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, true, null),
        ), $arguments);
    }

    public function testSignature3()
    {
        $arguments = $this->factory->createArgumentMetadata([$this, 'signature3']);

        $this->assertEquals(array(
            new ArgumentMetadata('bar', __NAMESPACE__.'\FakeClassThatDoesNotExist', false, false, null),
            new ArgumentMetadata('baz', 'Fake\ImportedAndFake', false, false, null),
        ), $arguments);
    }

    public function testSignature4()
    {
        $arguments = $this->factory->createArgumentMetadata([$this, 'signature4']);

        $this->assertEquals(array(
            new ArgumentMetadata('foo', null, false, true, 'default'),
            new ArgumentMetadata('bar', null, false, true, 500),
            new ArgumentMetadata('baz', null, false, true, []),
        ), $arguments);
    }

    public function testSignature5()
    {
        $arguments = $this->factory->createArgumentMetadata([$this, 'signature5']);

        $this->assertEquals(array(
            new ArgumentMetadata('foo', 'array', false, true, null),
            new ArgumentMetadata('bar', null, false, false, null),
        ), $arguments);
    }

    /**
     * @requires PHP 5.6
     */
    public function testVariadicSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new VariadicController(), 'action']);

        $this->assertEquals(array(
            new ArgumentMetadata('foo', null, false, false, null),
            new ArgumentMetadata('bar', null, true, false, null),
        ), $arguments);
    }

    /**
     * @requires PHP 7.0
     */
    public function testBasicTypesSignature()
    {
        $arguments = $this->factory->createArgumentMetadata([new BasicTypesController(), 'action']);

        $this->assertEquals(array(
            new ArgumentMetadata('foo', 'string', false, false, null),
            new ArgumentMetadata('bar', 'int', false, false, null),
            new ArgumentMetadata('baz', 'float', false, false, null),
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

    private function signature4($foo = 'default', $bar = 500, $baz = [])
    {

    }

    private function signature5(array $foo = null, $bar)
    {

    }
}
