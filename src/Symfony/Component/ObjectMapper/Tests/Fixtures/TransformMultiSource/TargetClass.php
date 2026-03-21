<?php

declare(strict_types=1);

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformMultiSource;

use Symfony\Component\ObjectMapper\Attribute\Map;

class TargetClass
{
    public function __construct(
        #[Map(sources: ['subClassA', 'subClassB'])]
        public MultiplePropertiesClass $multiplePropertiesClass,
    ) {
    }
}
