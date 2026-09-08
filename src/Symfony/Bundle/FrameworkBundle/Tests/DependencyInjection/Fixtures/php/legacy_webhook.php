<?php

$container->loadFromExtension('framework', [
    'webhook' => ['signing_algorithm' => 'sha512'],
]);
