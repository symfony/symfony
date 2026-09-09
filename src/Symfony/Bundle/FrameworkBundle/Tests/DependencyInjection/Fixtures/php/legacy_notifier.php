<?php

$container->loadFromExtension('framework', [
    'notifier' => ['texter_transports' => ['twilio' => 'null']],
]);
