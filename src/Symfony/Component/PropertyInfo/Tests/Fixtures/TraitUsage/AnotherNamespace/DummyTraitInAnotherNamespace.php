<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace;

trait DummyTraitInAnotherNamespace
{
    /**
     * @var DummyInAnotherNamespace
     */
    public $dummyInAnotherNamespace;

    /**
     * @param DummyInAnotherNamespace $dummyInConstructor
     */
    public function __construct($dummyInConstructor = null)
    {
    }

    /**
     * @return DummyInAnotherNamespace
     */
    public function getMethodInTraitInAnotherNamespace()
    {
        return new DummyInAnotherNamespace();
    }

    /**
     * @return DummyInAnotherNamespace
     */
    public function getOverriddenMethodInTraitInAnotherNamespace()
    {
        return new DummyInAnotherNamespace();
    }

    /**
     * @param DummyInAnotherNamespace $value
     */
    public function setMutatorInTraitInAnotherNamespace($value)
    {
    }
}
