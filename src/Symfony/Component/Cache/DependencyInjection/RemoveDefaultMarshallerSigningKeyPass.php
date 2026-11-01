<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the signing key of "cache.default_marshaller" when no secret is configured.
 *
 * The key is derived from the "kernel.secret" parameter, which is defined by
 * FrameworkBundle only. Without it, the marshaller doesn't sign values.
 *
 * @internal
 */
class RemoveDefaultMarshallerSigningKeyPass implements CompilerPassInterface
{
    private const SIGNING_KEY_ID = '.cache.default_marshaller.signing_key';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::SIGNING_KEY_ID)) {
            return;
        }

        if ($container->hasParameter('kernel.secret') && !\in_array($container->getParameter('kernel.secret'), ['', null], true)) {
            return;
        }

        $container->removeDefinition(self::SIGNING_KEY_ID);

        if (!$container->hasDefinition('cache.default_marshaller')) {
            return;
        }

        $definition = $container->getDefinition('cache.default_marshaller');
        $signingKey = $definition->getArguments()[2] ?? null;

        if ($signingKey instanceof ServiceClosureArgument && self::SIGNING_KEY_ID === (string) $signingKey->getValues()[0]) {
            $definition->replaceArgument(2, null);
        }
    }
}
