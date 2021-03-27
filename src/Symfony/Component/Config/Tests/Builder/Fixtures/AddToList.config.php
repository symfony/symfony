<?php

use Symfony\Config\AddToListConfig;

return static function (AddToListConfig $config) {
    $config->translator()->fallback(['sv', 'fr', 'es']);
    $config->translator()->source('\\Acme\\Foo', 'yellow');
    $config->translator()->source('\\Acme\\Bar', 'green');

    $config->messenger()
        ->routing('Foo\\Message')->senders(['workqueue']);
    $config->messenger()
        ->routing('Foo\\DoubleMessage')->senders(['sync', 'workqueue']);

    $config->messenger()->receiving()
        ->color('blue')
        ->priority(10);
    $config->messenger()->receiving()
        ->color('red')
        ->priority(5);
};
