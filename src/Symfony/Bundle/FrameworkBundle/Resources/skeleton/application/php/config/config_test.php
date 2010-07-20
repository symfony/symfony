<?php

$loader->import('config_dev.php');

$container->loadFromExtension('web', 'config', array(
    'toolbar' => false,
));

$container->loadFromExtension('kernel', 'test', array(
));
