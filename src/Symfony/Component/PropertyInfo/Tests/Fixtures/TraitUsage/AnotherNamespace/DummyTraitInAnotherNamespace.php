<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace;

/**
 * @template T
 */
trait DummyTraitInAnotherNamespace
{
    /**
     * @var DummyInAnotherNamespace
     */
    public $dummyInAnotherNamespace;

    /**
     * @param DummyInAnotherNamespace $dummyInConstructor
     * @param list<T>                 $templateInConstructor
     */
    public function __construct($dummyInConstructor = null, $templateInConstructor = [])
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
     * @param list<T> $value
     */
    public function setTemplateInTraitInAnotherNamespace($value)
    {
    }

    /**
     * @param DummyInAnotherNamespace $value
     */
    public function setMutatorInTraitInAnotherNamespace($value)
    {
    }
}
