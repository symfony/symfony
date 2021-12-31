<?php

namespace Symfony\Component\PropertyAccess\Tests\Constraint;

use PHPUnit\Framework\Constraint\Constraint;

final class ObjectHasInitializedProperty extends Constraint
{
    private string $attributeName;

    public function __construct(string $attributeName)
    {
        $this->attributeName = $attributeName;
    }

    public function toString(): string
    {
        return sprintf(
            'has initialized attribute "%s"',
            $this->attributeName
        );
    }

    protected function matches($other): bool
    {
        $propertyRefl = new \ReflectionProperty($other::class, $this->attributeName);
        $propertyRefl->setAccessible(true);
        return $propertyRefl->isInitialized($other);

//        return strpos(var_export($other, true), "'{$this->attributeName}' =>") !== false;
        // @codeCoverageIgnoreEnd
    }

    protected function failureDescription($other): string
    {
        return sprintf(
            'object %s',
            $this->toString()
        );
    }

    protected function attributeName(): string
    {
        return $this->attributeName;
    }
}
