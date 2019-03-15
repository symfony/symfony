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
    private $useFqcnAsFallback = false;

    /**
     * @param string      $tag                The name of the tag identifying the target services
     * @param string|null $indexAttribute     The name of the attribute that defines the key referencing each service in the tagged collection
     * @param string|null $defaultIndexMethod The static method that should be called to get each service's key when their tag doesn't define the previous attribute
     * @param bool        $useFqcnAsFallback  Whether the FQCN of the service should be used as index when neither the attribute nor the method are defined
     */
    public function __construct(string $tag, string $indexAttribute = null, string $defaultIndexMethod = null, bool $useFqcnAsFallback = false)
    {
        parent::__construct([]);

        $this->tag = $tag;
        $this->indexAttribute = $indexAttribute;
        $this->defaultIndexMethod = $defaultIndexMethod ?: ('getDefault'.str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $indexAttribute ?? ''))).'Name');
        $this->useFqcnAsFallback = $useFqcnAsFallback;
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

    public function useFqcnAsFallback(): bool
    {
        return $this->useFqcnAsFallback;
    }
}
