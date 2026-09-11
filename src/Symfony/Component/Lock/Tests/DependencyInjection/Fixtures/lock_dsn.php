<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->extension('lock', 'redis://example.com');
};
