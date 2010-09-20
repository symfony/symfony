<?php

$container->loadFromExtension('app', 'config', array(
    'charset'       => 'UTF-8',
    'error_handler' => null,
    'csrf-secret'   => 'xxxxxxxxxx',
    'router'        => array('resource' => '%kernel.root_dir%/config/routing.php'),
    'validation'    => array('enabled' => true, 'annotations' => true),
    'templating'    => array(
        'escaping'        => 'htmlspecialchars'
        #'assets_version' => "SomeVersionScheme",
    ),
    #'user' => array(
    #    'default_locale' => "fr",
    #    'session' => array(
    #        'name' => "SYMFONY",
    #        'type' => "Native",
    #        'lifetime' => "3600",
    #    )
    #),
));

// Twig Configuration
/*
$container->loadFromExtension('twig', 'config', array('auto_reload' => true));
*/

// Doctrine Configuration
/*
$container->loadFromExtension('doctrine', 'dbal', array(
    'dbname'   => 'xxxxxxxx',
    'user'     => 'xxxxxxxx',
    'password' => '',
));
$container->loadFromExtension('doctrine', 'orm');
*/

// Swiftmailer Configuration
/*
$container->loadFromExtension('swiftmailer', 'config', array(
    'transport'  => "smtp",
    'encryption' => "ssl",
    'auth_mode'  => "login",
    'host'       => "smtp.gmail.com",
    'username'   => "xxxxxxxx",
    'password'   => "xxxxxxxx",
));
*/
