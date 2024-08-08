<?php

namespace Symfony\Component\TypeInfo\Type;

use Symfony\Component\TypeInfo\Type;

interface NullableTypeInterface
{
    public function isNullable(): bool;
    public function asNonNullable(): Type;
}
