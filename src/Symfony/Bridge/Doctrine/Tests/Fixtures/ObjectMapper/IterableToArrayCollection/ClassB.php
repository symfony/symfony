<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\ObjectMapper\Transform\IterableToArrayCollection;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

class ClassB
{
    /** @param Collection<int, ClassA> $collection */
    public function __construct(
        #[Map(transform: new IterableToArrayCollection(new MapCollection(targetClass: ClassA::class)))]
        public Collection $collection = new ArrayCollection()
    ) {
    }
}
