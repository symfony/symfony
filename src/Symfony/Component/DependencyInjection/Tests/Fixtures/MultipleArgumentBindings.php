<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;

final class MultipleArgumentBindings
{
    public function __construct(
        #[TaggedIterator('my_tag'), TaggedLocator('another_tag')]
        object $collection
    ) {
    }
}
