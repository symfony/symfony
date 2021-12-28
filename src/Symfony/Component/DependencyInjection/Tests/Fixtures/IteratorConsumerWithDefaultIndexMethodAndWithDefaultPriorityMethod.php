<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class IteratorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod
{
    public function __construct(
        #[TaggedIterator(tag: 'foo_bar', defaultIndexMethod: 'getDefaultFooName', defaultPriorityMethod: 'getPriority')]
        private iterable $param,
    ) {
    }

    public function getParam(): iterable
    {
        return $this->param;
    }
}
