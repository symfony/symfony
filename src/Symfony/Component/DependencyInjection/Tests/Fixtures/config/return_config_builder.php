<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

$config = new AcmeConfig();
$config->color('red');

return $config;
