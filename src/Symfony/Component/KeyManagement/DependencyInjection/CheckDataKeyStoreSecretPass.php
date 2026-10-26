<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * Reports a data key store without a secret when the container compiles, instead of when the store is first used.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class CheckDataKeyStoreSecretPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('key_management.store') || $container->hasParameter('kernel.secret')) {
            return;
        }

        $secret = $container->getDefinition('key_management.store')->getArguments()[4] ?? null;

        if ($secret instanceof Parameter && 'kernel.secret' === (string) $secret) {
            throw new LogicException('The "key_management.store" needs a secret to authenticate its data keys: set "key_management.store.secret", or define the "kernel.secret" parameter, for example with "framework.secret".');
        }
    }
}
