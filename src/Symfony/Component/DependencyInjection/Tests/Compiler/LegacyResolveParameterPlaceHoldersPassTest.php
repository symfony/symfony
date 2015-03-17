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
    public function testFactoryClassParametersShouldBeResolved()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $compilerPass = new ResolveParameterPlaceHoldersPass();

        $container = new ContainerBuilder();
        $container->setParameter('foo.factory.class', 'FooFactory');
        $fooDefinition = $container->register('foo', '%foo.factory.class%');
        $fooDefinition->setFactoryClass('%foo.factory.class%');
        $compilerPass->process($container);
        $fooDefinition = $container->getDefinition('foo');

        $this->assertSame('FooFactory', $fooDefinition->getFactoryClass());
    }
}
