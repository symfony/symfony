<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class TaggedLocator extends Autowire
{
    public function __construct(
        public string $tag,
        public ?string $indexAttribute = null,
        public ?string $defaultIndexMethod = null,
        public ?string $defaultPriorityMethod = null,
        public string|array $exclude = [],
        public bool $excludeSelf = true,
    ) {
        parent::__construct(new ServiceLocatorArgument(new TaggedIteratorArgument($tag, $indexAttribute, $defaultIndexMethod, true, $defaultPriorityMethod, (array) $exclude, $excludeSelf)));
    }
}
