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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AssetsContextPass implements CompilerPassInterface
{
    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assets.context')) {
            return;
        }

        if (!$container->hasDefinition('router.request_context')) {
            $container->setParameter('asset.request_context.base_path', $container->getParameter('asset.request_context.base_path') ?? '');
            $container->setParameter('asset.request_context.secure', $container->getParameter('asset.request_context.secure') ?? false);

            return;
        }

        $context = $container->getDefinition('assets.context');

        if (null === $container->getParameter('asset.request_context.base_path')) {
            $context->replaceArgument(1, (new Definition('string'))->setFactory([new Reference('router.request_context'), 'getBaseUrl']));
        }

        if (null === $container->getParameter('asset.request_context.secure')) {
            $context->replaceArgument(2, (new Definition('bool'))->setFactory([new Reference('router.request_context'), 'isSecure']));
        }
    }
}
