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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Inline service definitions where this is possible.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InlineServiceDefinitionsPass extends AbstractRecursivePass implements RepeatablePassInterface
{
    private $repeatedPass;
    private $inlinedServiceIds = array();

    /**
     * {@inheritdoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        $this->repeatedPass = $repeatedPass;
    }

    /**
     * Returns an array of all services inlined by this pass.
     *
     * The key is the inlined service id and its value is the list of services it was inlined into.
     *
     * @return array
     */
    public function getInlinedServiceIds()
    {
        return $this->inlinedServiceIds;
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if ($value instanceof ArgumentInterface) {
            // Reference found in ArgumentInterface::getValues() are not inlineable
            return $value;
        }
        if ($value instanceof Reference && $this->container->hasDefinition($id = (string) $value)) {
            $definition = $this->container->getDefinition($id);

            if ($this->isInlineableDefinition($id, $definition, $this->container->getCompiler()->getServiceReferenceGraph())) {
                $this->container->log($this, sprintf('Inlined service "%s" to "%s".', $id, $this->currentId));
                $this->inlinedServiceIds[$id][] = $this->currentId;

                if ($definition->isShared()) {
                    return $definition;
                }
                $value = clone $definition;
            }
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Checks if the definition is inlineable.
     *
     * @return bool If the definition is inlineable
     */
    private function isInlineableDefinition($id, Definition $definition, ServiceReferenceGraph $graph)
    {
        if (!$definition->isShared()) {
            return true;
        }

        if ($definition->isPublic() || $definition->isLazy()) {
            return false;
        }

        if (!$graph->hasNode($id)) {
            return true;
        }

        if ($this->currentId == $id) {
            return false;
        }

        $ids = array();
        foreach ($graph->getNode($id)->getInEdges() as $edge) {
            $ids[] = $edge->getSourceNode()->getId();
        }

        if (count(array_unique($ids)) > 1) {
            return false;
        }

        if (count($ids) > 1 && is_array($factory = $definition->getFactory()) && ($factory[0] instanceof Reference || $factory[0] instanceof Definition)) {
            return false;
        }

        return true;
    }
}
