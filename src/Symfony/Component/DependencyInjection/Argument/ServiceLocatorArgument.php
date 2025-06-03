<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * Represents a closure acting as a service locator.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServiceLocatorArgument implements ArgumentInterface
{
    private array $values;
    private ?TaggedIteratorArgument $taggedIteratorArgument = null;

    public function __construct(array|TaggedIteratorArgument $values = [])
    {
        if ($values instanceof TaggedIteratorArgument) {
            $this->taggedIteratorArgument = $values;
            $values = [];
        }

        $this->setValues($values);
    }

    public function getTaggedIteratorArgument(): ?TaggedIteratorArgument
    {
        return $this->taggedIteratorArgument;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
    }
}
