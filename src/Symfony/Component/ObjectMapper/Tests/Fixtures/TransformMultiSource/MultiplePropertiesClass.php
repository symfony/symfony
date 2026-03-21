<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformMultiSource;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\ClassRule;

class MultiplePropertiesClass
{
    public function __construct(
        #[Map(sources: ['foo'], if: new \Symfony\Component\ObjectMapper\Condition\SourceClass(SubClassA::class))]
        public string $foo = 'FOO',
        #[Map(sources: ['bar'], if: new \Symfony\Component\ObjectMapper\Condition\SourceClass(SubClassB::class))]
        public string $bar = 'BAR'
    ) {
    }
}
