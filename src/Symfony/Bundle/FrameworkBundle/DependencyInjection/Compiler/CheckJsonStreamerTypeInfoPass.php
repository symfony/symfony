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
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Reports the TypeInfo services that JsonStreamer needs as missing.
 *
 * Without this pass, the container only complains about a non-existent
 * "type_info.resolver" service, which says nothing about TypeInfo being
 * disabled or not installed.
 *
 * @internal
 */
class CheckJsonStreamerTypeInfoPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('json_streamer.stream_writer') || $container->has('type_info.resolver')) {
            return;
        }

        $error = 'JsonStreamer support cannot be enabled as the TypeInfo component is not '.(interface_exists(TypeResolverInterface::class) ? 'enabled. Try setting "type_info.enabled" to true.' : 'installed. Try running "composer require symfony/type-info".');

        foreach (['type_info.resolver' => TypeResolverInterface::class, 'type_info.type_context_factory' => TypeContextFactory::class] as $id => $class) {
            if (!$container->has($id)) {
                $container->register($id, $class)->addError($error);
            }
        }
    }
}
