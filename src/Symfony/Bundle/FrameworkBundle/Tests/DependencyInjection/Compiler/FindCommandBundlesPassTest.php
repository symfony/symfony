<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FindCommandBundlesPass;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FindCommandBundlesPassTest extends TestCase
{
    public function testOnlyBundlesOverridingRegisterCommandsAreListed()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'InertBundle' => InertBundle::class,
            'CommandRegisteringBundle' => CommandRegisteringBundle::class,
            'DiBundle' => DiBundle::class,
            'MissingBundle' => 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MissingBundle',
        ]);

        (new FindCommandBundlesPass())->process($container);

        $this->assertSame(['CommandRegisteringBundle'], $container->getParameter('console.command.bundles'));
    }

    public function testParameterIsSetWhenNoBundleOverridesRegisterCommands()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['InertBundle' => InertBundle::class]);

        (new FindCommandBundlesPass())->process($container);

        $this->assertSame([], $container->getParameter('console.command.bundles'));
    }
}

class InertBundle extends Bundle
{
}

class CommandRegisteringBundle extends Bundle
{
    public function registerCommands(Application $application): void
    {
    }
}

class DiBundle extends AbstractBundle
{
}
