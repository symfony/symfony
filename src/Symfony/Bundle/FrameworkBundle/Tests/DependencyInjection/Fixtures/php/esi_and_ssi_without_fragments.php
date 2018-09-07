<?php

$container->loadFromExtension('framework', array(
    'fragments' => array(
        'enabled' => false,
    ),
    'esi' => array(
        'enabled' => true,
    ),
    'ssi' => array(
        'enabled' => true,
    ),
));
