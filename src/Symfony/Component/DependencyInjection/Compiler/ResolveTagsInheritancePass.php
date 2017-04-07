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

use Symfony\Component\DependencyInjection\ChildDefinition;

/**
 * Applies tags inheritance to definitions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveTagsInheritancePass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof ChildDefinition || !$value->getInheritTags()) {
            return parent::processValue($value, $isRoot);
        }
        $value->setInheritTags(false);

        if (!$this->container->has($parent = $value->getParent())) {
            return parent::processValue($value, $isRoot);
        }

        $parentDef = $this->container->findDefinition($parent);

        if ($parentDef instanceof ChildDefinition) {
            $this->processValue($parentDef);
        }

        foreach ($parentDef->getTags() as $k => $v) {
            foreach ($v as $v) {
                $value->addTag($k, $v);
            }
        }

        return parent::processValue($value, $isRoot);
    }
}
