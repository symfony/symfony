<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformMultiSource;

class SourceClass
{
    public function __construct(
        public SubClassA $subClassA = new SubClassA(),
        public SubClassB $subClassB = new SubClassB(),
    ) {
    }
}
