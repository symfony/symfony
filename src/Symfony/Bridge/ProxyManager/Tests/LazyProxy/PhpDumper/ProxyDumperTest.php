<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\Tests\Instantiator;

use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests for {@see \Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper}
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @covers \Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper
 */
class ProxyDumperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ProxyDumper
     */
    protected $dumper;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->dumper = new ProxyDumper();
    }

    /**
     * @dataProvider getProxyCandidates
     *
     * @param Definition $definition
     * @param bool       $expected
     */
    public function testIsProxyCandidate(Definition $definition, $expected)
    {
        $this->assertSame($expected, $this->dumper->isProxyCandidate($definition));
    }

    public function testGetProxyCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyCode($definition);

        $this->assertStringMatchesFormat(
            '%Aclass SymfonyBridgeProxyManagerLazyProxyTestsInstantiatorProxyDumperTest%aextends%w'
                . '\Symfony\Bridge\ProxyManager\LazyProxy\Tests\Instantiator%a',
            $code
        );
    }

    public function testGetProxyFactoryCode()
    {
        $definition = new Definition(__CLASS__);

        $definition->setLazy(true);

        $code = $this->dumper->getProxyFactoryCode($definition, 'foo');

        $this->assertStringMatchesFormat(
            '%wif ($lazyLoad) {%w$container = $this;%wreturn $this->services[\'foo\'] = new '
            . 'SymfonyBridgeProxyManagerLazyProxyTestsInstantiatorProxyDumperTest_%s(%wfunction '
            . '(&$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface $proxy) use ($container) {'
            . '%w$proxy->setProxyInitializer(null);%w$wrappedInstance = $container->getFooService(false);'
            . '%wreturn true;%w}%w);%w}%w',
            $code
        );
    }

    /**
     * @return array
     */
    public function getProxyCandidates()
    {
        $definitions = array(
            array(new Definition(__CLASS__), true),
            array(new Definition('stdClass'), true),
            array(new Definition('foo' . uniqid()), false),
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
