<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->extension('semaphore', 'redis://example.com');
};
