<?php

namespace Symfony\Component\Form\Configurator;

class ValidatorConfigurator implements ConfiguratorInterface
{
    protected $metaData = null;
    protected $classMetaData = null;

    public function __construct(MetaDataInterface $metaData)
    {
        $this->metaData = $metaData;
    }

    public function initialize($object)
    {
        $this->classMetaData = $this->metaData->getClassMetaData(get_class($object));
    }

    public function getClass($fieldName)
    {

    }

    public function getOptions($fieldName)
    {

    }

    public function isRequired($fieldName)
    {
        return $this->classMetaData->getPropertyMetaData($fieldName)->hasConstraint('NotNull')
                || $this->classMetaData->getPropertyMetaData($fieldName)->hasConstraint('NotEmpty');
    }
}