<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'session' => array(
        'storage_id'        => 'session.storage.native',
        'handler_id'        => 'session.handler.native_file',
        'name'              => '_SYMFONY',
        'lifetime'          => 2012,
        'path'              => '/sf2',
        'domain'            => 'sf2.example.com',
        'secure'            => false,
        'httponly'          => false,
        'cookie_lifetime'   => 86400,
        'cookie_path'       => '/',
        'cookie_domain'     => 'example.com',
        'cookie_secure'     => true,
        'cookie_httponly'   => true,
    ),
));
