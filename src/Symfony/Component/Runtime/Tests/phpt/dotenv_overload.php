<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$_SERVER['SOME_VAR'] = 'ccc';
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return fn (Request $request, array $context) => new Response('OK Request '.$context['SOME_VAR']);
