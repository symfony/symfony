<?php

$container->loadFromExtension('security', 'config', array(
    'role_hierarchy' => array(
        'ROLE_ADMIN' => 'ROLE_USER',
        'ROLE_SUPER_ADMIN' => array('ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'),
        'ROLE_REMOTE' => 'ROLE_USER,ROLE_ADMIN',
    )
));
