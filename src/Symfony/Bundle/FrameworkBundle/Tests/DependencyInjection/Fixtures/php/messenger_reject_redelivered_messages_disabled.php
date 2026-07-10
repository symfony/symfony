<?php

$container->loadFromExtension('framework', [
    'messenger' => [
        'reject_redelivered_messages' => false,
    ],
]);
