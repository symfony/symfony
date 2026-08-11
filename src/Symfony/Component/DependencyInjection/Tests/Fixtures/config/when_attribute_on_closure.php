<?php

use Symfony\Component\DependencyInjection\Attribute\WhenClassExists;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return #[WhenClassExists('Redis')] function (ContainerConfigurator $container) {
};
