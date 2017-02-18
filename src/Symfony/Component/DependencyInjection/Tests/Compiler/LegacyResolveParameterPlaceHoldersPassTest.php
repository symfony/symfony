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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @group legacy
 */
class LegacyResolveParameterPlaceHoldersPassTest extends TestCase
{
    public function testFactoryClassParametersShouldBeResolved()
    {
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
