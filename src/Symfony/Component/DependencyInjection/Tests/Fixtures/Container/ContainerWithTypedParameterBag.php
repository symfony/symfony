<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ContainerWithTypedParameterBag extends \Symfony\Component\DependencyInjection\Container
{
    public function __construct(?ParameterBagInterface $parameterBag = null)
    {
        parent::__construct($parameterBag);
    }
}
