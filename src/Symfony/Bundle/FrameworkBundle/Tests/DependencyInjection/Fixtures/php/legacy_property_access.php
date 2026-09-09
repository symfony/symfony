<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_property_accessor', 'property_accessor')->public();
    $container->extension('framework', [
        'property_access' => [
            'magic_call' => true,
            'magic_get' => true,
            'magic_set' => false,
            'throw_exception_on_invalid_index' => true,
            'throw_exception_on_invalid_property_path' => false,
            'wildcard_reads' => true,
        ],
    ]);
};
