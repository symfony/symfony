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
    private $tag;
    private $serviceName;

    public function __construct($serviceName, $tag)
    {
        $this->serviceName = $serviceName;
        $this->tag = $tag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->serviceName)) {
            return;
        }

        $clearers = array();
        foreach ($container->findTaggedServiceIds($this->tag) as $id => $attributes) {
            $clearers[] = new Reference($id);
        }

        $container->getDefinition($this->serviceName)->replaceArgument(0, $clearers);
    }
}
