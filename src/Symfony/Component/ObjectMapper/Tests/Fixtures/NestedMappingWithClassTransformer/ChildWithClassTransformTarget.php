<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer;

class ChildWithClassTransformTarget
{
    public string $name;
    public bool $classTransformed = false;

    public static function createFromSource(ChildWithClassTransformSource $value): self
    {
        $target = new self();
        $target->name = $value->name;
        $target->classTransformed = true;

        return $target;
    }
}
