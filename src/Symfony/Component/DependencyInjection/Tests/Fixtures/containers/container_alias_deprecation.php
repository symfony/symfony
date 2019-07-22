<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container
    ->register('foo', 'stdClass')
    ->setPublic(true)
;

$container
    ->setAlias('alias_for_foo_deprecated', 'foo')
    ->setDeprecated(true)
    ->setPublic(true);

$container
    ->setAlias('alias_for_foo_non_deprecated', 'foo')
    ->setPublic(true);

return $container;
