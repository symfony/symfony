<?php

$container->loadFromExtension('framework', array(
    'session' => array(
        'storage_id'  => 'pdo',
        'db_table'    => 'table',
        'db_id_col'   => 'id',
        'db_data_col' => 'data',
        'db_time_col' => 'time',
    ),
));
