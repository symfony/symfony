<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfigBuilder;

return static function (AcmeConfigBuilder $config) {
    $config->color('blue');
};
