<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class FooContainerConfigurator
{
    private $configurator;

    public function __construct(ContainerConfigurator $configurator)
    {
        $this->configurator = $configurator;
    }

    public function foo()
    {
        $this->configurator->parameters()->set('foo', 'bar');
    }
}
