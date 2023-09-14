<?php

namespace Symfony\Component\DependencyInjection\Attribute;

use ReflectionParameter;

interface AutowireableAttributeInterface
{
    public function getAutowireAttribute(ReflectionParameter $parameter): Autowire;
}
