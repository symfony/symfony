<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'middlewares' => array(
            'doctrine_transaction' => array(
                'entity_manager_name' => 'foobar',
            ),
        ),
    ),
));
