<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Simplify collecting and registering tagged services.
 *
 * Adds convenience functionality to register tagged services
 * with a parent service inside Bundle#build(ContainerBuilder $container).
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 *
 * @example
 *
 * Collect all services tagged with 'my_tag_name' and inject them
 * into 'my_aggregating_service' using the 'addListener' method. Using
 * one argument only and injecting the service:
 *
 *      $container->addCompilerPass(new CollectSimpleTaggedServicesPass(
 *          'my_tag_name', 'my_aggregating_service', 'addListener'
 *      ));
 *
 * Collect all services and work with additional attributes:
 *
 *      $container->addCompilerPass(new CollectSimpleTaggedServicesPass(
 *          'my_tag_name', 'my_aggregating_service', 'addListener',
 *          function ($id, $attributes) {
 *              return array(new Reference($id), $attributes['foo']);
 *          }
 *      ));
 */
class CollectSimpleTaggedServicesPass implements CompilerPassInterface
{
    private $tagName;
    private $service;
    private $collectMethodName;
    private $argumentWrangler;

    /**
     * @param string $tagName
     * @param string $service
     * @param string $collectMethodName
     * @param callable|null $argumentWrangler
     */
    public function __construct($tagName, $service, $collectMethodName, $argumentWrangler = null)
    {
        $this->tagName = $tagName;
        $this->service = $service;
        $this->collectMethodName = $collectMethodName;

        $this->argumentWrangler = $argumentWrangler ?: function ($id) {
            return array(new Reference($id));
        };
    }

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has($this->service)) {
            return;
        }

        $taggedServiceIds = $container->findTaggedServiceIds($this->tagName);
        $definition = $container->findDefinition($this->service);
        $wrangler = $this->argumentWrangler;

        foreach ($taggedServiceIds as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall(
                    $this->collectMethodName,
                    $wrangler($id, $attributes)
                );
            }
        }
    }
}

