<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

return static function (AcmeConfig $config) {
    $config->color('blue');
};
