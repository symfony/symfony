<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\BindTaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\BindTaggedLocator;

final class MultipleArgumentBindings
{
    public function __construct(
        #[BindTaggedIterator('my_tag'), BindTaggedLocator('another_tag')]
        object $collection
    ) {
    }
}
