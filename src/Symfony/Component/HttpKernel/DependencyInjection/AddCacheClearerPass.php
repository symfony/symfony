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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the cache clearers.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 */
class AddCacheClearerPass implements CompilerPassInterface
{
    private $cacheClearerId;
    private $cacheClearerTag;

    public function __construct($cacheClearerId = 'cache_clearer', $cacheClearerTag = 'kernel.cache_clearer')
    {
        $this->cacheClearerId = $cacheClearerId;
        $this->cacheClearerTag = $cacheClearerTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->cacheClearerId)) {
            return;
        }

        $clearers = array();
        foreach ($container->findTaggedServiceIds($this->cacheClearerTag, true) as $id => $attributes) {
            $clearers[] = new Reference($id);
        }

        $container->getDefinition($this->cacheClearerId)->replaceArgument(0, $clearers);
    }
}
