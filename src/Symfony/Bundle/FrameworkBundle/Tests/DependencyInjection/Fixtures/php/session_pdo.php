<?php

$container->loadFromExtension('app', 'config', array(
    'router' => array(
        'resource' => '%kernel.root_dir%/config/routing.xml',
    ),
    'session' => array(
        'storage_id'      => 'pdo',
        'pdo.db_table'    => 'table',
        'pdo.db_id_col'   => 'id',
        'pdo.db_data_col' => 'data',
        'pdo.db_time_col' => 'time',
    ),
    'templating' => array(
        'engine' => 'php'
    ),
));
