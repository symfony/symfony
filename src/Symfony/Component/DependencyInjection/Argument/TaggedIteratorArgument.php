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
 * Represents a collection of services found by tag name to lazily iterate over.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class TaggedIteratorArgument extends IteratorArgument
{
    private $tag;
    private $indexAttribute;
    private $defaultIndexMethod;

    public function __construct(string $tag, string $indexAttribute = null, string $defaultIndexMethod = null)
    {
        parent::__construct([]);

        $this->tag = $tag;
        $this->indexAttribute = $indexAttribute ?: null;

        if ($indexAttribute) {
            $this->defaultIndexMethod = $defaultIndexMethod ?: ('getDefault'.str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $indexAttribute))).'Name');
        }
    }

    public function getTag()
    {
        return $this->tag;
    }

    public function getIndexAttribute(): ?string
    {
        return $this->indexAttribute;
    }

    public function getDefaultIndexMethod(): ?string
    {
        return $this->defaultIndexMethod;
    }
}
