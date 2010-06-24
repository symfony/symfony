<?php

namespace Symfony\Components\Form\Configurator;

interface ConfiguratorInterface
{
    public function initialize($object);

    public function getClass($fieldName);

    public function getOptions($fieldName);

    public function isRequired($fieldName);
}