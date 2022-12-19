<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @template TClassLevelProperty
 * @template TClassLevelMethod
 */
class TypeVariableDummy
{
    /**
     * @var TClassLevelProperty
     */
    public $property;

    private mixed $propertyOfGetter;

    /**
     * @param TClassLevelProperty $promotedPropertyWithParamTypeDeclaration
     */
    public function __construct(
        public mixed $promotedPropertyWithParamTypeDeclaration,
        /**
         * @var TClassLevelProperty $promotedPropertyWithVarTypeDeclaration
         */
        public mixed $promotedPropertyWithVarTypeDeclaration,
    )
    {

    }

    /**
     * @return TClassLevelMethod
     */
    public function getClassLevelTemplateDeclaration()
    {
        return $this->propertyOfGetter;
    }
}
