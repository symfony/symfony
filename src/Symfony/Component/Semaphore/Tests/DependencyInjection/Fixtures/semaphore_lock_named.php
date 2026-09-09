<?php

$container->loadFromExtension('semaphore', [
    'default' => 'lock://',
    'bar' => 'lock://foo',
]);
