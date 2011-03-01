<?php

$container->loadFromExtension('framework', array(
    'session' => array(
        'storage_id'         => 'mongodb',
        'mongodb.collection' => 'collection',
        'mongodb.id_field'   => 'id',
        'mongodb.data_field' => 'data',
        'mongodb.time_field' => 'time',
    ),
));
