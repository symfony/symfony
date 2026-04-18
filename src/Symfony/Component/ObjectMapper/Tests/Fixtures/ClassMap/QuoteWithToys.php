<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

final class QuoteWithToys
{
    /**
     * @param list<Cost> $toys
     */
    public function __construct(
        public string $id,
        public array $toys = [],
    ) {
    }
}
