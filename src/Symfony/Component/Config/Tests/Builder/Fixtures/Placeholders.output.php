<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'enabled' => '%env(bool:FOO_ENABLED)%',
    'favorite_float' => '%eulers_number%',
    'good_integers' => '%env(json:MY_INTEGERS)%',
];
