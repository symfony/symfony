<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ClassC
{
    /** @param Collection<int, ClassA> $collection */
    public function __construct(
        public Collection $collection = new ArrayCollection()
    ) {
    }
}
