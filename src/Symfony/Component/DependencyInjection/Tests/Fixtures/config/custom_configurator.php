<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\FooContainerConfigurator;

return function (FooContainerConfigurator $c) {
    $c->foo();
};
