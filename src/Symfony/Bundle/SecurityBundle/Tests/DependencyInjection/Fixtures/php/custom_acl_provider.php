<?php

$this->load('container1.php', $container);

$container->loadFromExtension('security', [
    'acl' => [
        'provider' => 'foo',
    ],
]);
