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
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Inline service definitions where this is possible.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InlineServiceDefinitionsPass extends AbstractRecursivePass implements RepeatablePassInterface
{
    private $cloningIds = [];
    private $inlinedServiceIds = [];

    /**
     * {@inheritdoc}
     */
    public function setRepeatedPass(RepeatedPass $repeatedPass)
    {
        // no-op for BC
    }

    /**
     * Returns an array of all services inlined by this pass.
     *
     * The key is the inlined service id and its value is the list of services it was inlined into.
     *
     * @deprecated since version 3.4, to be removed in 4.0.
     *
     * @return array
     */
    public function getInlinedServiceIds()
    {
        @trigger_error('Calling InlineServiceDefinitionsPass::getInlinedServiceIds() is deprecated since Symfony 3.4 and will be removed in 4.0.', \E_USER_DEPRECATED);

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

        if ($value instanceof Definition && $this->cloningIds) {
            if ($value->isShared()) {
                return $value;
            }
            $value = clone $value;
        }

        if (!$value instanceof Reference || !$this->container->hasDefinition($id = $this->container->normalizeId($value))) {
            return parent::processValue($value, $isRoot);
        }

        $definition = $this->container->getDefinition($id);

        if (!$this->isInlineableDefinition($id, $definition, $this->container->getCompiler()->getServiceReferenceGraph())) {
            return $value;
        }

        $this->container->log($this, sprintf('Inlined service "%s" to "%s".', $id, $this->currentId));
        $this->inlinedServiceIds[$id][] = $this->currentId;

        if ($definition->isShared()) {
            return $definition;
        }

        if (isset($this->cloningIds[$id])) {
            $ids = array_keys($this->cloningIds);
            $ids[] = $id;

            throw new ServiceCircularReferenceException($id, \array_slice($ids, array_search($id, $ids)));
        }

        $this->cloningIds[$id] = true;
        try {
            return $this->processValue($definition);
        } finally {
            unset($this->cloningIds[$id]);
        }
    }

    /**
     * Checks if the definition is inlineable.
     *
     * @return bool If the definition is inlineable
     */
    private function isInlineableDefinition($id, Definition $definition, ServiceReferenceGraph $graph)
    {
        if ($definition->getErrors() || $definition->isDeprecated() || $definition->isLazy() || $definition->isSynthetic()) {
            return false;
        }

        if (!$definition->isShared()) {
            return true;
        }

        if ($definition->isPublic() || $definition->isPrivate()) {
            return false;
        }

        if (!$graph->hasNode($id)) {
            return true;
        }

        if ($this->currentId == $id) {
            return false;
        }

        $ids = [];
        $isReferencedByConstructor = false;
        foreach ($graph->getNode($id)->getInEdges() as $edge) {
            $isReferencedByConstructor = $isReferencedByConstructor || $edge->isReferencedByConstructor();
            if ($edge->isWeak() || $edge->isLazy()) {
                return false;
            }
            $ids[] = $edge->getSourceNode()->getId();
        }

        if (!$ids) {
            return true;
        }

        if (\count(array_unique($ids)) > 1) {
            return false;
        }

        if (\count($ids) > 1 && \is_array($factory = $definition->getFactory()) && ($factory[0] instanceof Reference || $factory[0] instanceof Definition)) {
            return false;
        }

        return $this->container->getDefinition($ids[0])->isShared();
    }
}
