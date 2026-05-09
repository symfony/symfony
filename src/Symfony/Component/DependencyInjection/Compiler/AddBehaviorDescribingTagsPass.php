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

/**
 * This pass allows bundles to extend the list of behavior-describing tags.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AddBehaviorDescribingTagsPass implements CompilerPassInterface
{
    private const DEFAULT_TAGS = [
        'container.do_not_inline',
        'container.service_locator',
        'container.service_subscriber',
    ];

    /**
     * @param list<string> $tags
     */
    public function __construct(
        private array $tags = [],
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $tags = $container->hasParameter('container.behavior_describing_tags') ? $container->getParameter('container.behavior_describing_tags') : self::DEFAULT_TAGS;

        $container->setParameter(
            'container.behavior_describing_tags',
            array_unique(array_merge($tags, $this->tags))
        );
    }
}
