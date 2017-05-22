<?php

// We load the configuration in severals steps, to ensure the keys (name) are
// preserved.

// AMQP need to be enabled in order to use AMQP Fetcher
$container->loadFromExtension('framework', array(
    'amqp' => array(
        'connections' => array(
            'default' => array(),
            'another_one' => array(),
        ),
    ),
    'worker' => array(
        'cli_title_prefix' => 'foobar',
    ),
));

/* worker.fetcher.amqp */
$container->loadFromExtension('framework', array(
    'worker' => array(
        'fetchers' => array(
            'amqps' => array(
                'queue_a' => null,
                'queue_b' => array(),
                'queue_c_1' => array(
                    'queue_name' => 'queue_c',
                ),
            ),
        ),
    ),
));
$container->loadFromExtension('framework', array(
    'worker' => array(
        'fetchers' => array(
            'amqps' => array(
                'queue_d_1' => array(
                    'name' => 'queue_d',
                ),
                'queue_e (key not used)' => array(
                    'name' => 'queue_e',
                    'queue_name' => 'queue_e',
                ),
                'queue_f' => array(
                    'connection' => 'another_one',
                    'auto_ack' => true,
                ),
            ),
        ),
    ),
));

/* worker.fetcher.service */
$container->loadFromExtension('framework', array(
    'worker' => array(
        'fetchers' => array(
            'services' => array(
                'service_a' => null,
                'service_b' => array(),
                'service_c_1' => array(
                    'service' => 'service_c',
                ),
            ),
        ),
    ),
));
$container->loadFromExtension('framework', array(
    'worker' => array(
        'fetchers' => array(
            'services' => array(
                'service_d_1' => array(
                    'name' => 'service_d',
                ),
                'service_e (key not used)' => array(
                    'name' => 'service_e',
                    'service' => 'service_e',
                ),
            ),
        ),
    ),
));

/* worker.fetcher.buffer */
$container->loadFromExtension('framework', array(
    'worker' => array(
        'fetchers' => array(
            'buffers' => array(
                'queue_a' => null,
                'queue_b' => array(),
                'queue_c' => array(
                    'wrap' => 'queue_c_1',
                ),
            ),
        ),
    ),
));
$container->loadFromExtension('framework', array(
    'worker' => array(
        'fetchers' => array(
            'buffers' => array(
                'queue_d (key not used)' => array(
                    'name' => 'queue_d_1',
                    'wrap' => 'queue_d',
                ),
                'queue_e' => array(
                    'max_messages' => 12,
                    'max_buffering_time' => 60,
                ),
                'service_a' => null,
            ),
        ),
    ),
));

/* worker.router.direct */
$container->loadFromExtension('framework', array(
    'worker' => array(
        'routers' => array(
            'directs' => array(
                'queue_a' => array(
                    'consumer' => 'a_consumer_service',
                ),
                'queue_b_1' => array(
                    'consumer' => 'a_consumer_service',
                    'name' => 'queue_b',
                ),
                'queue_c (key is not used)' => array(
                    'consumer' => 'a_consumer_service',
                    'fetcher' => 'queue_c',
                    'name' => 'router_c',
                ),
                'router_d' => array(
                    'consumer' => 'a_consumer_service',
                    'fetcher' => 'queue_d',
                ),
            ),
        ),
    ),
));
$container->loadFromExtension('framework', array(
    'worker' => array(
        'routers' => array(
            'round_robins' => array(
                'router_c_and_d' => array(
                    'groups' => array('router_c', 'router_d'),
                ),
            ),
        ),
    ),
));

/* worker.router.direct */
$container->loadFromExtension('framework', array(
    'worker' => array(
        'workers' => array(
            'worker_d' => array(
                'router' => 'router_d',
            ),
        ),
    ),
));
$container->loadFromExtension('framework', array(
    'worker' => array(
        'workers' => array(
            'worker_service_a' => array(
                'fetcher' => 'service_a',
                'consumer' => 'a_consumer_service',
            ),
        ),
    ),
));
