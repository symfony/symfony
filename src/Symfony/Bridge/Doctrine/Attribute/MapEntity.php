<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Attribute;

/**
 * Indicates that a controller argument should receive an Entity.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapEntity
{
    public function __construct(
        public ?string $class = null,
        public ?string $objectManager = null,
        public ?string $expr = null,
        public ?array $mapping = null,
        public ?array $exclude = null,
        public ?bool $stripNull = null,
        public array|string|null $id = null,
        public ?bool $evictCache = null,
        public bool $disabled = false,
    ) {
    }

    public function withDefaults(self $defaults, ?string $class): static
    {
        $clone = clone $this;
        $clone->class ??= class_exists($class ?? '') ? $class : null;
        $clone->objectManager ??= $defaults->objectManager;
        $clone->expr ??= $defaults->expr;
        $clone->mapping ??= $defaults->mapping;
        $clone->exclude ??= $defaults->exclude ?? [];
        $clone->stripNull ??= $defaults->stripNull ?? false;
        $clone->id ??= $defaults->id;
        $clone->evictCache ??= $defaults->evictCache ?? false;

        return $clone;
    }
}
