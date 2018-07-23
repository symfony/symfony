<?php

$container->loadFromExtension('framework', array(
    'form' => array(
        'csrf_protection' => array(
            'enabled' => false,
        ),
    ),
));
