<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace;

trait DummyTraitInAnotherNamespace
{
    /**
     * @var DummyInAnotherNamespace
     */
    public $dummyInAnotherNamespace;

    /**
     * @var DummyInAnotherNamespace
     */
    public $redeclaredPropertyInTraitInAnotherNamespace;

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
     * @return DummyInAnotherNamespace
     */
    abstract public function getAbstractMethodInTraitInAnotherNamespace();

    /**
     * @return DummyInAnotherNamespace
     */
    abstract public function getOverriddenAbstractMethodInTraitInAnotherNamespace();

    /**
     * @param DummyInAnotherNamespace $value
     */
    public function setMutatorInTraitInAnotherNamespace($value)
    {
    }
}
