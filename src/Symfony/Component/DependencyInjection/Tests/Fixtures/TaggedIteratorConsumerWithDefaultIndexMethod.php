<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @deprecated since Symfony 8.1
 */
final class TaggedIteratorConsumerWithDefaultIndexMethod
{
    public function __construct(
        #[AutowireIterator('foo_bar', defaultIndexMethod: 'getDefaultFooName')]
        private iterable $param,
    ) {
    }

    public function getParam(): iterable
    {
        return $this->param;
    }
}
