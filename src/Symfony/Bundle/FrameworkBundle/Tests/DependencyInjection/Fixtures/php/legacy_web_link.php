<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_add_link_header_listener', 'web_link.add_link_header_listener')->public();
    $container->extension('framework', [
        'web_link' => true,
    ]);
};
