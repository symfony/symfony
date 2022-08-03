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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Throws an exception for any Definitions that have errors and still exist.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class DefinitionErrorExceptionPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof Definition || !$value->hasErrors()) {
            return parent::processValue($value, $isRoot);
        }

        if ($isRoot && !$value->isPublic()) {
            $graph = $this->container->getCompiler()->getServiceReferenceGraph();
            $runtimeException = false;
            foreach ($graph->getNode($this->currentId)->getInEdges() as $edge) {
                if (!$edge->getValue() instanceof Reference || ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE !== $edge->getValue()->getInvalidBehavior()) {
                    $runtimeException = false;
                    break;
                }
                $runtimeException = true;
            }
            if ($runtimeException) {
                return parent::processValue($value, $isRoot);
            }
        }

        // only show the first error so the user can focus on it
        $errors = $value->getErrors();
        $message = reset($errors);

        throw new RuntimeException($message);
    }
}
