<?php

$container->loadFromExtension('kernel', 'config', array(
    'charset'       => 'UTF-8',
    'error_handler' => null,
));

$container->loadFromExtension('web', 'config', array(
    'router' => array('resource' => '%kernel.root_dir%/config/routing.php'),
));

$container->loadFromExtension('web', 'templating', array(
    'escaping'       => "htmlspecialchars",
    'assets_version' => "SomeVersionScheme",
));

$container->loadFromExtension('doctrine', 'dbal', array(
    'dbname'   => 'xxxxxxxx',
    'user'     => 'root',
    'password' => '',
));

$container->loadFromExtension('doctrine', 'orm', array());

$container->loadFromExtension('swift', 'mailer', array(
    'transport' => "gmail",
    'username'  => "xxxxxxxx",
    'password'  => "xxxxxxxx",
));
