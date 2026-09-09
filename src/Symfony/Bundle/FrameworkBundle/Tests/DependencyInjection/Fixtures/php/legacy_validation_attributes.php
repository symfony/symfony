<?php

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'validation' => [
        'enabled' => true,
        'enable_attributes' => true,
    ],
]);

$container->setAlias('validator.alias', 'validator')->setPublic(true);
