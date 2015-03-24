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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds services tagged kernel.fragment_renderer as HTTP content rendering strategies.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FragmentRendererPass implements CompilerPassInterface
{
    private $handlerService;
    private $rendererTag;

    /**
     * @param string $handlerService Service name of the fragment handler in the container
     * @param string $rendererTag    Tag name used for fragments
     */
    public function __construct($handlerService = 'fragment.handler', $rendererTag = 'kernel.fragment_renderer')
    {
        $this->handlerService = $handlerService;
        $this->rendererTag = $rendererTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->handlerService)) {
            return;
        }

        $definition = $container->getDefinition($this->handlerService);
        foreach ($container->findTaggedServiceIds($this->rendererTag) as $id => $tags) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as fragment renderer are lazy-loaded.', $id));
            }

            if ($def->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must not be abstract as fragment renderer are lazy-loaded.', $id));
            }

            $refClass = new \ReflectionClass($container->getParameterBag()->resolveValue($def->getClass()));
            $interface = 'Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface';
            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            foreach ($tags as $tag) {
                if (!isset($tag['alias'])) {
                    trigger_error(sprintf('Service "%s" will have to define the "alias" attribute on the "%s" tag as of Symfony 3.0.', $id, $this->rendererTag), E_USER_DEPRECATED);

                    // register the handler as a non-lazy-loaded one
                    $definition->addMethodCall('addRenderer', array(new Reference($id)));
                } else {
                    $definition->addMethodCall('addRendererService', array($tag['alias'], $id));
                }
            }
        }
    }
}
