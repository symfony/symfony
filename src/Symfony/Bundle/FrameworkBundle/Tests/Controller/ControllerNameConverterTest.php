<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameConverter;
use Symfony\Bundle\FrameworkBundle\Tests\Logger;
use Symfony\Bundle\FrameworkBundle\Tests\Kernel;

require_once __DIR__.'/../Kernel.php';
require_once __DIR__.'/../Logger.php';

class ControllerNameConverterTest extends TestCase
{
    public function testToShortNotation()
    {
        $kernel = new Kernel();
        $kernel->boot();
        $converter = new ControllerNameConverter($kernel);

        $this->assertEquals('FooBundle:Foo:index', $converter->toShortNotation('Symfony\Bundle\FooBundle\Controller\FooController::indexAction'), '->toShortNotation() converts a class::method string to the short a:b:c notation');

        try {
            $converter->toShortNotation('foo');
            $this->fail('->toShortNotation() throws an \InvalidArgumentException if the controller is not a class::method string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->toShortNotation() throws an \InvalidArgumentException if the controller is not a class::method string');
        }

        try {
            $converter->toShortNotation('Symfony\Bundle\FooBundle\Controller\FooController::bar');
            $this->fail('->toShortNotation() throws an \InvalidArgumentException if the method does not end with Action');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->toShortNotation() throws an \InvalidArgumentException if the method does not end with Action');
        }

        try {
            $converter->toShortNotation('Symfony\Bundle\FooBundle\FooController::barAction');
            $this->fail('->toShortNotation() throws an \InvalidArgumentException if the class does not end with Controller');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->toShortNotation() throws an \InvalidArgumentException if the class does not end with Controller');
        }

        try {
            $converter->toShortNotation('FooBar\Bundle\FooBundle\Controller\FooController::barAction');
            $this->fail('->toShortNotation() throws an \InvalidArgumentException if the class does not belongs to a known namespace');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->toShortNotation() throws an \InvalidArgumentException if the class does not belongs to a known namespace');
        }
    }

    public function testFromShortNotation()
    {
        $kernel = new Kernel();
        $kernel->boot();
        $logger = new Logger();
        $converter = new ControllerNameConverter($kernel, $logger);

        $this->assertEquals('Symfony\Bundle\FrameworkBundle\Controller\DefaultController::indexAction', $converter->fromShortNotation('FrameworkBundle:Default:index'), '->fromShortNotation() converts a short a:b:c notation string to a class::method string');

        try {
            $converter->fromShortNotation('foo:');
            $this->fail('->fromShortNotation() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->toShortNotation() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        }

        try {
            $converter->fromShortNotation('FooBundle:Default:index');
            $this->fail('->fromShortNotation() throws a \InvalidArgumentException if the class is found but does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->fromShortNotation() throws a \LogicException if the class is found but does not exist');
        }
    }
}
