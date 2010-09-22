<?php

$loader->import('config_dev.php');

$container->loadFromExtension('web', 'config', array(
    'error_handler' => false,
));

$container->loadFromExtension('webprofiler', 'config', array(
    'toolbar' => false,
    'intercept-redirects' => false,
));

$container->loadFromExtension('zend', 'logger', array(
    'priority' => 'debug',
));

$container->loadFromExtension('web', 'test');
