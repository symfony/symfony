<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Uid\Uuid47Transformer;

/**
 * Removes the UUIDv47 transformer when no secret is configured.
 *
 * The service falls back to the "kernel.secret" parameter, which is defined by
 * FrameworkBundle only. Keeping the service would make the container fail to
 * compile in applications that do not provide that parameter.
 *
 * @internal
 */
class RemoveUuid47TransformerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('uuid47_transformer') || $container->hasParameter('kernel.secret')) {
            return;
        }

        if ('%kernel.secret%' !== $container->getDefinition('uuid47_transformer')->getArgument(0)) {
            return;
        }

        $container->removeDefinition('uuid47_transformer');
        $container->removeAlias(Uuid47Transformer::class);
    }
}
