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

@trigger_error(sprintf('The %s class is deprecated since version 3.4 and will be removed in 4.0. Use tagged iterator arguments instead.', AddCacheClearerPass::class), E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the cache clearers.
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use tagged iterator arguments.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 */
class AddCacheClearerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('cache_clearer')) {
            return;
        }

        $clearers = array();
        foreach ($container->findTaggedServiceIds('kernel.cache_clearer', true) as $id => $attributes) {
            $clearers[] = new Reference($id);
        }

        $container->getDefinition('cache_clearer')->replaceArgument(0, $clearers);
    }
}
