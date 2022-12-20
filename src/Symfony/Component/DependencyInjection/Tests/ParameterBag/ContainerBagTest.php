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
        self::assertSame(['foo' => 'value'], $this->containerBag->all());
    }

    public function testHasAParameter()
    {
        self::assertTrue($this->containerBag->has('foo'));
        self::assertFalse($this->containerBag->has('bar'));
    }

    public function testGetParameter()
    {
        self::assertSame('value', $this->containerBag->get('foo'));
    }

    public function testGetParameterNotFound()
    {
        self::expectException(InvalidArgumentException::class);
        $this->containerBag->get('bar');
    }

    public function testInstanceOf()
    {
        self::assertInstanceOf(FrozenParameterBag::class, $this->containerBag);
        self::assertInstanceOf(ContainerBagInterface::class, $this->containerBag);
        self::assertInstanceOf(ContainerInterface::class, $this->containerBag);
    }
}
