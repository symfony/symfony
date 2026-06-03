<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * @deprecated since Symfony 8.1
 */
final class TaggedLocatorConsumerWithDefaultPriorityMethod
{
    public function __construct(
        #[AutowireLocator('foo_bar', defaultPriorityMethod: 'getPriority')]
        private ContainerInterface $locator,
    ) {
    }

    public function getLocator(): ContainerInterface
    {
        return $this->locator;
    }
}
