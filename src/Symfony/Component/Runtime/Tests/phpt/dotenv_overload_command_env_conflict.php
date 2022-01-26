<?php

$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'env_var_name' => 'ENV_MODE',
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return static function (): void {};
