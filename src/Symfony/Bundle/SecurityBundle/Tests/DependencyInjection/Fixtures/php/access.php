<?php

$container->loadFromExtension('security', 'config', array(
    'access_control' => array(
        array('path' => '/blog/524', 'role' => 'ROLE_USER', 'requires_channel' => 'https'),
        array('path' => '/blog/.*', 'attributes' => array('_controller' => '.*\\BlogBundle\\.*'), 'role' => 'IS_AUTHENTICATED_ANONYMOUSLY'),
    ),
));
