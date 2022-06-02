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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Replaces aliases passed to lock store factory with actual service definitions.
 *
 * @author Jack Thomas <jack.thomas@solidalpha.com>
 */
class ReplaceLockStoreDsnByActualDefinitionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $factory = $definition->getFactory();

            if (
                $factory === null ||
                $factory[0] !== StoreFactory::class
            ) {
                continue;
            }

            $connection = $definition->getArgument(0);

            if (!is_string($connection)) {
                continue;
            }

            if (!$connection[0] === '@') {
                continue;
            }

            $definition->replaceArgument(0, new Reference(substr($connection, 1)));
        }
    }
}
