<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;

return function (ContainerConfigurator $containerConfigurator) {
    $containerConfigurator->parameters()
        ->set('unit_enum', FooUnitEnum::BAR)
        ->set('enum_array', [FooUnitEnum::BAR, FooUnitEnum::FOO]);

    $services = $containerConfigurator->services();

    $services->defaults()->public();

    $services->set('service_container', ContainerInterface::class)
        ->synthetic();

    $services->set(FooClassWithEnumAttribute::class)
        ->args([FooUnitEnum::BAR]);
};
