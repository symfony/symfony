<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Registers the cache warmers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddCacheWarmerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $cacheWarmerId;
    private $cacheWarmerTag;

    public function __construct($cacheWarmerId = 'cache_warmer', $cacheWarmerTag = 'kernel.cache_warmer')
    {
        $this->cacheWarmerId = $cacheWarmerId;
        $this->cacheWarmerTag = $cacheWarmerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->cacheWarmerId)) {
            return;
        }

        $warmers = $this->findAndSortTaggedServices($this->cacheWarmerTag, $container);

        if (empty($warmers)) {
            return;
        }

        $container->getDefinition($this->cacheWarmerId)->replaceArgument(0, $warmers);
    }
}
