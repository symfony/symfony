<?php

$this->load('merge_import.php', $container);

$container->loadFromExtension('security', array(
    'providers' => array(
        'default' => array('id' => 'foo'),
    ),

    'firewalls' => array(
        'main' => array(
            'form_login' => false,
            'http_basic' => null,
        ),
    ),

    'role_hierarchy' => array(
        'FOO' => array('MOO'),
    )
));