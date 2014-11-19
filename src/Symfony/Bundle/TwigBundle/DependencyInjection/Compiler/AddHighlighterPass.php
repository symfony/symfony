<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the code highlighters.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class AddHighlighterPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('twig.extension.code')) {
            return;
        }

        $highlighters = array();
        foreach ($container->findTaggedServiceIds('code_highlighter') as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $highlighters[$priority][] = new Reference($id);
        }

        if (empty($highlighters)) {
            return;
        }

        // sort by priority and flatten
        krsort($highlighters);
        $highlighters = call_user_func_array('array_merge', $highlighters);

        $container->getDefinition('twig.extension.code')->replaceArgument(3, $highlighters);
    }
}
