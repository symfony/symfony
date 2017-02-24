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
use Symfony\Component\DependencyInjection\ChildDefinition;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveClassPass implements CompilerPassInterface
{
    private $changes = array();

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition instanceof ChildDefinition || $definition->isSynthetic() || null !== $definition->getClass()) {
                continue;
            }
            if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)++$/', $id)) {
                $this->changes[strtolower($id)] = $id;
                $definition->setClass($id);
            }
        }
    }

    /**
     * @internal
     *
     * @deprecated since 3.3, to be removed in 4.0.
     */
    public function getChanges()
    {
        $changes = $this->changes;
        $this->changes = array();

        return $changes;
    }
}
