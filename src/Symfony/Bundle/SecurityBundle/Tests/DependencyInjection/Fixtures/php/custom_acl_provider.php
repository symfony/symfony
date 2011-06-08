<?php

$this->load('container1.php', $container);

$container->loadFromExtension('security', array(
    'acl' => array(
        'provider' => 'foo',
    )
));
