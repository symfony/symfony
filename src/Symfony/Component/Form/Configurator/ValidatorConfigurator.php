<?php

namespace Symfony\Component\Form\Configurator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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