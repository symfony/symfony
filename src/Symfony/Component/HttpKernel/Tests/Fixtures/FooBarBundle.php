<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @deprecated Deprecated since version 2.6, to be removed in 3.0.
 */
class FooBarBundle extends Bundle
{
    // We need a full namespaced bundle instance to test isClassInActiveBundle
}
