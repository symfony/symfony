<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'trusted_proxies' => array('127.0.0.1', '10.0.0.1'),
));
