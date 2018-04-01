<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symphony\Component\DependencyInjection\Definition;

/**
 * Tests for {@see \Symphony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class ProxyDumperTest extends TestCase
{
    /**
     * @var ProxyDumper
     */
    protected $dumper;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->dumper = new ProxyDumper();
    }

    /**
     * @dataProvider getProxyCandidates
     */
    public function testIsProxyCandidate(Definition $definition, bool $expected)
    {
        $this->assertSame($expected, $this->dumper->isProxyCandidate($definition));
    }

    public function testGetProxyCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyCode($definition);

        $this->assertStringMatchesFormat(
            '%Aclass ProxyDumperTest%aextends%w'
                .'\Symphony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\ProxyDumperTest%a',
            $code
        );
    }

    public function testDeterministicProxyCode()
    {
        $definition = new Definition(__CLASS__);
        $definition->setLazy(true);

        $this->assertSame($this->dumper->getProxyCode($definition), $this->dumper->getProxyCode($definition));
    }

    public function testGetProxyFactoryCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyFactoryCode($definition, 'foo', '$this->getFoo2Service(false)');

        $this->assertStringMatchesFormat(
            '%A$wrappedInstance = $this->getFoo2Service(false);%w$proxy->setProxyInitializer(null);%A',
            $code
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Missing factory code to construct the service "foo".
     */
    public function testGetProxyFactoryCodeWithoutCustomMethod()
    {
        $definition = new Definition(__CLASS__);
        $definition->setLazy(true);
        $this->dumper->getProxyFactoryCode($definition, 'foo');
    }

    /**
     * @return array
     */
    public function getProxyCandidates()
    {
        $definitions = array(
            array(new Definition(__CLASS__), true),
            array(new Definition('stdClass'), true),
            array(new Definition(uniqid('foo', true)), false),
            array(new Definition(), false),
        );

        array_map(
            function ($definition) {
                $definition[0]->setLazy(true);
            },
            $definitions
        );

        return $definitions;
    }
}
