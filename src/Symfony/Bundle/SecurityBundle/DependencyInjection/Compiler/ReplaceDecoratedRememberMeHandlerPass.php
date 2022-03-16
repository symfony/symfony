<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Bundle\SecurityBundle\RememberMe\DecoratedRememberMeHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Replaces the DecoratedRememberMeHandler services with the real definition.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
final class ReplaceDecoratedRememberMeHandlerPass implements CompilerPassInterface
{
    private const HANDLER_TAG = 'security.remember_me_handler';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $handledFirewalls = [];
        foreach ($container->findTaggedServiceIds(self::HANDLER_TAG) as $definitionId => $rememberMeHandlerTags) {
            $definition = $container->findDefinition($definitionId);
            if (DecoratedRememberMeHandler::class !== $definition->getClass()) {
                continue;
            }

            // get the actual custom remember me handler definition (passed to the decorator)
            $realRememberMeHandler = $container->findDefinition((string) $definition->getArgument(0));
            if (null === $realRememberMeHandler) {
                throw new \LogicException(sprintf('Invalid service definition for custom remember me handler; no service found with ID "%s".', (string) $definition->getArgument(0)));
            }

            foreach ($rememberMeHandlerTags as $rememberMeHandlerTag) {
                // some custom handlers may be used on multiple firewalls in the same application
                if (\in_array($rememberMeHandlerTag['firewall'], $handledFirewalls, true)) {
                    continue;
                }

                $rememberMeHandler = clone $realRememberMeHandler;
                $rememberMeHandler->addTag(self::HANDLER_TAG, $rememberMeHandlerTag);
                $container->setDefinition('security.authenticator.remember_me_handler.'.$rememberMeHandlerTag['firewall'], $rememberMeHandler);

                $handledFirewalls[] = $rememberMeHandlerTag['firewall'];
            }
        }
    }
}
