<?php

$container->loadFromExtension('framework', [
    'semaphore' => 'redis://localhost',
]);
