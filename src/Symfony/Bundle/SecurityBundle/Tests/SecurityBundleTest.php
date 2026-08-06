<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RemoveSecurityDataCollectorListenerPass;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SecurityBundleTest extends TestCase
{
    public function testTheDataCollectorListenerRemovalPassIsRegistered()
    {
        $bundle = new SecurityBundle();
        $container = new ContainerBuilder();
        $container->registerExtension($bundle->getContainerExtension());
        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        $passes = array_merge($passes, $container->getCompilerPassConfig()->getOptimizationPasses());
        $passes = array_merge($passes, $container->getCompilerPassConfig()->getBeforeRemovingPasses());
        $passes = array_merge($passes, $container->getCompilerPassConfig()->getRemovingPasses());
        $passes = array_merge($passes, $container->getCompilerPassConfig()->getAfterRemovingPasses());

        foreach ($passes as $pass) {
            if ($pass instanceof RemoveSecurityDataCollectorListenerPass) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail(\sprintf('"%s" is not registered by "%s".', RemoveSecurityDataCollectorListenerPass::class, SecurityBundle::class));
    }
}
