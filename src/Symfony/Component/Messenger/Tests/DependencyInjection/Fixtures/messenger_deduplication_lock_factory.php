<?php

$container->loadFromExtension('messenger', [
    'deduplication' => [
        'lock_factory' => 'lock.dedup.factory',
    ],
]);
