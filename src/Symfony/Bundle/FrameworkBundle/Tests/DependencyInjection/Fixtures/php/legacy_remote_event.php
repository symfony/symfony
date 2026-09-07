<?php

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container) {
    $container->services()->alias('test_remote_event_handler', 'remote_event.messenger.handler')->public();
    $container->extension('framework', [
        'remote_event' => null,
    ]);
};
