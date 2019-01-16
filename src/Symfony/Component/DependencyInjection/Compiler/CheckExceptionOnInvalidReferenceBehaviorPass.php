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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Checks that all references are pointing to a valid service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckExceptionOnInvalidReferenceBehaviorPass extends AbstractRecursivePass
{
    private $serviceLocatorContextIds = [];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->serviceLocatorContextIds = [];
        foreach ($container->findTaggedServiceIds('container.service_locator_context') as $id => $tags) {
            $this->serviceLocatorContextIds[$id] = $tags[0]['id'];
            $container->getDefinition($id)->clearTag('container.service_locator_context');
        }

        try {
            return parent::process($container);
        } finally {
            $this->serviceLocatorContextIds = [];
        }
    }

    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Reference) {
            return parent::processValue($value, $isRoot);
        }
        if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE < $value->getInvalidBehavior() || $this->container->has($id = (string) $value)) {
            return $value;
        }

        $currentId = $this->currentId;
        $graph = $this->container->getCompiler()->getServiceReferenceGraph();

        if (isset($this->serviceLocatorContextIds[$currentId])) {
            $currentId = $this->serviceLocatorContextIds[$currentId];
            $locator = $this->container->getDefinition($this->currentId)->getFactory()[0];

            foreach ($locator->getArgument(0) as $k => $v) {
                if ($v->getValues()[0] === $value) {
                    if ($k !== $id) {
                        $currentId = $k.'" in the container provided to "'.$currentId;
                    }
                    throw new ServiceNotFoundException($id, $currentId);
                }
            }
        }

        if ('.' === $currentId[0] && $graph->hasNode($currentId)) {
            foreach ($graph->getNode($currentId)->getInEdges() as $edge) {
                if (!$edge->getValue() instanceof Reference || ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE < $edge->getValue()->getInvalidBehavior()) {
                    continue;
                }
                $sourceId = $edge->getSourceNode()->getId();

                if ('.' !== $sourceId[0]) {
                    $currentId = $sourceId;
                    break;
                }
            }
        }

        throw new ServiceNotFoundException($id, $currentId);
    }
}
