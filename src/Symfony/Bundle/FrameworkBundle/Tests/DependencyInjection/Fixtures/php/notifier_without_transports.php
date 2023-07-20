<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'notifier' => [
        'enabled' => true,
    ],
]);
