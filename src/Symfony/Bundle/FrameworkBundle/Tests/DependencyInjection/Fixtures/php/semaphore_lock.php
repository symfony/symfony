<?php

$container->loadFromExtension('framework', [
    'lock' => 'flock',
    'semaphore' => 'lock://',
]);
