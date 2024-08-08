<?php

namespace Symfony\Component\TypeInfo\Type;

use Symfony\Component\TypeInfo\Type;

interface CompositeTypeInterface
{
    /**
     * @return list<Type>
     */
    public function getTypes(): array;
}
