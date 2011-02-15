<?php

$container->loadFromExtension('framework', array(
    'session' => array(
        'storage_id'      => 'pdo',
        'pdo.db_table'    => 'table',
        'pdo.db_id_col'   => 'id',
        'pdo.db_data_col' => 'data',
        'pdo.db_time_col' => 'time',
    ),
));
