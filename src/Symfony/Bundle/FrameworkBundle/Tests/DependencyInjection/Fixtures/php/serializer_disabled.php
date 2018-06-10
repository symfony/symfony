<?php

$container->loadFromExtension('framework', array(
    'serializer' => array(
        'enabled' => false,
    ),
    'messenger' => array(
        'serializer' => false,
    ),
));
