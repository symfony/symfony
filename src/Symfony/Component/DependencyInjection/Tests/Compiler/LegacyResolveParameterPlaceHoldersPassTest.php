<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @group legacy
 */
class LegacyResolveParameterPlaceHoldersPassTest extends \PHPUnit_Framework_TestCase
{
    private $compilerPass;
    private $container;
    private $fooDefinition;

    protected function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->compilerPass = new ResolveParameterPlaceHoldersPass();
        $this->container = $this->createContainerBuilder();
        $this->compilerPass->process($this->container);
        $this->fooDefinition = $this->container->getDefinition('foo');
    }

    private function createContainerBuilder()
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('foo.class', 'Foo');
        $containerBuilder->setParameter('foo.factory.class', 'FooFactory');
        $fooDefinition = $containerBuilder->register('foo', '%foo.class%');
        $fooDefinition->setFactoryClass('%foo.factory.class%');

        return $containerBuilder;
    }

    public function testFactoryClassParametersShouldBeResolved()
    {
        $this->assertSame('FooFactory', $this->fooDefinition->getFactoryClass());
    }
}
