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

use Symfony\Component\DependencyInjection\Reference;

/**
 * Represents a closure acting as a service locator.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServiceLocatorArgument implements ArgumentInterface
{
    use ReferenceSetArgumentTrait;

    private $taggedIteratorArgument = null;

    /**
     * @param Reference[]|TaggedIteratorArgument $values
     */
    public function __construct(array|TaggedIteratorArgument $values = [])
    {
        if ($values instanceof TaggedIteratorArgument) {
            $this->taggedIteratorArgument = $values;
            $this->values = [];
        } else {
            $this->setValues($values);
        }
    }

    public function getTaggedIteratorArgument(): ?TaggedIteratorArgument
    {
        return $this->taggedIteratorArgument;
    }
}
