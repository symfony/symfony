<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

return function (AcmeConfig $config, string $env) {
    if ('prod' === $env) {
        $config->color('blue');
    } else {
        $config->color('red');
    }
};
