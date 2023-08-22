<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;

final class TaggedLocatorConsumerWithDefaultPriorityMethod
{
    public function __construct(
        #[TaggedLocator(tag: 'foo_bar', defaultPriorityMethod: 'getPriority')]
        private ContainerInterface $locator,
    ) {
    }

    public function getLocator(): ContainerInterface
    {
        return $this->locator;
    }
}
