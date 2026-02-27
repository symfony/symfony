<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer;

class ParentTarget
{
    public string $name;
    public ChildWithClassTransformTarget $childWithClassTransformer;
    public ChildWithoutClassTransformerTarget $childWithoutClassTransformer;
    public ChildWithClassTransformTarget $childWithBothTransformers;
    public bool $transformed = false;

    public static function createFromSource(ParentTarget $value, ParentSource $source): self
    {
        $value->transformed = true;

        return $value;
    }

    public static function childPropertyTransformer(ChildWithoutClassTransformerSource $value, ParentSource $source): ChildWithoutClassTransformerTarget
    {
        $target = new ChildWithoutClassTransformerTarget();

        $target->name = 'child';
        $target->propertyTransformed = true;

        return $target;
    }

    public static function childBothTransformer(ChildWithClassTransformSource $value, ParentSource $source): ChildWithClassTransformSource
    {
        $value->name = 'both';

        return $value;
    }
}
