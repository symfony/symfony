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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal to be removed in 6.0
 */
class SessionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('session.factory')) {
            return;
        }

        // BC layer: Make "session" an alias of ".session.do-not-use" when not overridden by the user
        if (!$container->has('session')) {
            $alias = $container->setAlias('session', '.session.do-not-use');
            $alias->setDeprecated('symfony/framework-bundle', '5.3', 'The "%alias_id%" service and "SessionInterface" alias are deprecated, use "$requestStack->getSession()" instead.');
            // restore previous behavior
            $alias->setPublic(true);

            return;
        }

        if ($container->hasDefinition('session')) {
            $definition = $container->getDefinition('session');
            $definition->setDeprecated('symfony/framework-bundle', '5.3', 'The "%service_id%" service and "SessionInterface" alias are deprecated, use "$requestStack->getSession()" instead.');
        } else {
            $alias = $container->getAlias('session');
            $alias->setDeprecated('symfony/framework-bundle', '5.3', 'The "%alias_id%" and "SessionInterface" aliases are deprecated, use "$requestStack->getSession()" instead.');
            $definition = $container->findDefinition('session');
        }

        // Convert internal service `.session.do-not-use` into alias of `session`.
        $container->setAlias('.session.do-not-use', 'session');

        $bags = [
            'session.flash_bag' => $container->hasDefinition('session.flash_bag') ? $container->getDefinition('session.flash_bag') : null,
            'session.attribute_bag' => $container->hasDefinition('session.attribute_bag') ? $container->getDefinition('session.attribute_bag') : null,
        ];

        foreach ($definition->getArguments() as $v) {
            if (!$v instanceof Reference || !isset($bags[$bag = (string) $v]) || !\is_array($factory = $bags[$bag]->getFactory())) {
                continue;
            }

            if ([0, 1] !== array_keys($factory) || !$factory[0] instanceof Reference || !\in_array((string) $factory[0], ['session', '.session.do-not-use'], true)) {
                continue;
            }

            if ('get'.ucfirst(substr($bag, 8, -4)).'Bag' !== $factory[1]) {
                continue;
            }

            $bags[$bag]->setFactory(null);
        }
    }
}
