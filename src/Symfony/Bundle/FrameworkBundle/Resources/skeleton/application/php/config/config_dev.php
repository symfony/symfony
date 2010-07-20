<?php

$loader->import('config.php');

$container->getExtension('zend')->load('logger', array(
    'priority' => 'info',
    'path'     => '%kernel.logs_dir%/%kernel.environment%.log',
), $container);

$container->getExtension('web')->load('config', array(
    'toolbar' => true,
), $container);
