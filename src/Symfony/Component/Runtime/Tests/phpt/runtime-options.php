<?php

$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'env_var_names' => [
        'env_key' => 'ENV_MODE',
        'debug_key' => 'DEBUG_MODE',
    ],
];
require __DIR__.'/autoload.php';

return function (array $context): void {
    echo 'Env mode ', $context['ENV_MODE'], ', debug mode ', $context['DEBUG_MODE'];
};
