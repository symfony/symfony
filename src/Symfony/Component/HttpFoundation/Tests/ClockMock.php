<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

function time($asFloat = false)
{
    return Tests\time();
}

namespace Symfony\Component\HttpFoundation\Tests;

function time()
{
    return $_SERVER['REQUEST_TIME'];
}
