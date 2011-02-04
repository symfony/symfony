<?php

$this->load('merge_import.php', $container);

$container->loadFromExtension('security', 'config', array(
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