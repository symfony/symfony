<?php

$loader->import('config.php');

$container->loadFromExtension('zend', 'logger', array(
    'priority' => 'info',
    'path'     => '%kernel.logs_dir%/%kernel.environment%.log',
));

$container->loadFromExtension('web', 'config', array(
    'toolbar' => true,
));
