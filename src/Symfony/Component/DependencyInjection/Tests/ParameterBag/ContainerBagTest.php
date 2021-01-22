<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\ParameterBag;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ContainerBagTest extends TestCase
{
    /** @var ParameterBag */
    private $parameterBag;
    /** @var ContainerBag */
    private $containerBag;

    protected function setUp(): void
    {
        $this->parameterBag = new ParameterBag(['foo' => 'value']);
        $this->containerBag = new ContainerBag(new Container($this->parameterBag));
    }

    public function testGetAllParameters()
    {
        $this->assertSame(['foo' => 'value'], $this->containerBag->all());
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

    public function testGetParameterNotFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->containerBag->get('bar');
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf(FrozenParameterBag::class, $this->containerBag);
        $this->assertInstanceOf(ContainerBagInterface::class, $this->containerBag);
        $this->assertInstanceOf(ContainerInterface::class, $this->containerBag);
    }
}
