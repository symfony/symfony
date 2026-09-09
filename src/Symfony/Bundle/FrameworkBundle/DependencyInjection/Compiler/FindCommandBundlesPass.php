<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Lists the bundles that override the deprecated Bundle::registerCommands() method,
 * so that the console instantiates only those instead of all of them.
 *
 * @internal to be removed in Symfony 9.0
 */
class FindCommandBundlesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $bundles = [];

        foreach ($container->getParameter('kernel.bundles') as $name => $class) {
            if (is_subclass_of($class, Bundle::class) && Bundle::class !== new \ReflectionMethod($class, 'registerCommands')->getDeclaringClass()->getName()) {
                $bundles[] = $name;
            }
        }

        $container->setParameter('console.command.bundles', $bundles);
    }
}
