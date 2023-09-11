<?php

namespace Symfony\Component\DependencyInjection\Attribute;

use ReflectionParameter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

interface AutowireCustom
{
    public function buildReference(ContainerBuilder $container, ReflectionParameter $parameter): Reference;
}
