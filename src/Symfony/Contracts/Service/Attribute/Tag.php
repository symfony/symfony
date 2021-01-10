<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Service\Attribute;

/**
 * A generic tag implementation.
 *
 * This attribute holds meta information on the annotated class that can be processed by a service container.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Tag implements TagInterface
{
    /**
     * @param mixed[] $attributes
     */
    public function __construct(
        private string $name,
        private array $attributes = [],
        int $priority = 0,
    ) {
        $this->attributes['priority'] = $priority;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->attributes['priority'];
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
