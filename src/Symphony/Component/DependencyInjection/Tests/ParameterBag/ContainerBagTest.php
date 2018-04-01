<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\ParameterBag;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symphony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symphony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symphony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ContainerBagTest extends TestCase
{
    /** @var ParameterBag */
    private $parameterBag;
    /** @var ContainerBag */
    private $containerBag;

    public function setUp()
    {
        $this->parameterBag = new ParameterBag(array('foo' => 'value'));
        $this->containerBag = new ContainerBag(new Container($this->parameterBag));
    }

    public function testGetAllParameters()
    {
        $this->assertSame(array('foo' => 'value'), $this->containerBag->all());
    }

    public function testHasAParameter()
    {
        $this->assertTrue($this->containerBag->has('foo'));
        $this->assertFalse($this->containerBag->has('bar'));
    }

    public function testGetParameter()
    {
        $this->assertSame('value', $this->containerBag->get('foo'));
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testGetParameterNotFound()
    {
        $this->containerBag->get('bar');
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf(FrozenParameterBag::class, $this->containerBag);
        $this->assertInstanceOf(ContainerBagInterface::class, $this->containerBag);
        $this->assertInstanceOf(ContainerInterface::class, $this->containerBag);
    }
}
