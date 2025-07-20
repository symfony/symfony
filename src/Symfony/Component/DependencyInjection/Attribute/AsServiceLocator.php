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

/**
 * Declares a Service Locator.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsServiceLocator
{
    public function __construct(
        public string $tag,
        public string $indexAttribute = 'key',
        public ?string $defaultIndexMethod = null,
        public ?string $defaultPriorityMethod = null,
        public string|array $exclude = [],
        public bool $excludeSelf = true,
    ) {
    }
}
