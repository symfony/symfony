<?php

$container->loadFromExtension('framework', array(
    'amqp' => array(
        'connections' => array(
            'queue_staging' => array(
                'url' => 'amqp://foo:baz@rabbitmq:1234/staging',
            ),
            'queue_prod' => array(
                'url' => 'amqp://foo:bar@rabbitmq:1234/prod',
                'queues' => array(
                    array(
                        'name' => 'retry_strategy_exponential',
                        'retry_strategy' => 'exponential',
                        'retry_strategy_options' => array('offset' => 1, 'max' => 3),
                    ),
                    array(
                        'name' => 'arguments',
                        'arguments' => array(
                            'routing_keys' => 'my_routing_key',
                            'flags' => 2,
                        ),
                    ),
                ),
                'exchanges' => array(
                    array(
                        'name' => 'headers',
                        'arguments' => array(
                            'type' => 'headers',
                        ),
                    ),
                ),
            ),
        ),
        'default_connection' => 'queue_prod',
    ),
));
