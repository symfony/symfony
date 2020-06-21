<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

trigger_deprecation('symfony/dependency-injection', '5.2', 'The "%s" class is deprecated.', ResolvePrivatesPass::class);

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 5.2
 */
class ResolvePrivatesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isPrivate()) {
                $definition->setPublic(false);
                $definition->setPrivate(true);
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            if ($alias->isPrivate()) {
                $alias->setPublic(false);
                $alias->setPrivate(true);
            }
        }
    }
}
