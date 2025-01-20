<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final class TaggedLocatorConsumerWithDefaultIndexMethodAndWithDefaultPriorityMethod
{
    public function __construct(
        #[AutowireLocator('foo_bar', defaultIndexMethod: 'getDefaultFooName', defaultPriorityMethod: 'getPriority')]
        private ContainerInterface $locator,
    ) {
    }

    public function getLocator(): ContainerInterface
    {
        return $this->locator;
    }
}
