<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

if ('prod' !== $env) {
    return;
}

return function (AcmeConfig $config) {
    $config->color('blue');
};
