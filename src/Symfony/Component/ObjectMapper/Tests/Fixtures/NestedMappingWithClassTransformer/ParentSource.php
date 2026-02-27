<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ParentTarget::class, transform: [ParentTarget::class, 'createFromSource'])]
class ParentSource
{
    public string $name = 'parent';

    public ChildWithClassTransformSource $childWithClassTransformer;

    #[Map(transform: [ParentTarget::class, 'childPropertyTransformer'])]
    public ChildWithoutClassTransformerSource $childWithoutClassTransformer;

    #[Map(transform: [ParentTarget::class, 'childBothTransformer'])]
    public ChildWithClassTransformSource $childWithBothTransformers;

    public function __construct()
    {
        $this->childWithClassTransformer = new ChildWithClassTransformSource();
        $this->childWithoutClassTransformer = new ChildWithoutClassTransformerSource();
        $this->childWithBothTransformers = new ChildWithClassTransformSource();
    }
}
