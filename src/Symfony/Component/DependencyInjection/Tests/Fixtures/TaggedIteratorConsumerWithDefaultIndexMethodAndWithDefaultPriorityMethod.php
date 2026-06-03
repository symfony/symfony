<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @deprecated since Symfony 8.1
 */
final class TaggedIteratorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod
{
    public function __construct(
        #[AutowireIterator('foo_bar', defaultIndexMethod: 'getDefaultFooName', defaultPriorityMethod: 'getPriority')]
        private iterable $param,
    ) {
    }

    public function getParam(): iterable
    {
        return $this->param;
    }
}
