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

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.4 and will be removed in 4.0. Use tagged iterator arguments instead.', AddCacheWarmerPass::class), E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Registers the cache warmers.
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use tagged iterator arguments instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddCacheWarmerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('cache_warmer')) {
            return;
        }

        $warmers = $this->findAndSortTaggedServices('kernel.cache_warmer', $container);

        if (empty($warmers)) {
            return;
        }

        $container->getDefinition('cache_warmer')->replaceArgument(0, $warmers);
    }
}
