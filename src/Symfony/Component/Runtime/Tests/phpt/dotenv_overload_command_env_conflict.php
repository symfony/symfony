<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'env_var_name' => 'ENV_MODE',
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return static function (): void {};
