<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Bar\FooClass;
use stdClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\StdClassDecorator;

return function (ContainerConfigurator $c) {
    $s = $c->services();

    $s->set('decorated', stdClass::class);

    $s->set(null, StdClassDecorator::class)
        ->decorate('decorated', 'decorator42')
        ->args([ref('decorator42')]);

    $s->set('listener_aggregator', FooClass::class)->public()->args([tagged_iterator('listener')]);

    $s->set(null, stdClass::class)->tag('listener');
};
